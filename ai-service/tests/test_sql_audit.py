"""Tests for SQL Parameterization Audit Tool.

This test suite verifies the SQL audit tool correctly identifies SQL injection
vulnerabilities and properly recognizes safe parameterized queries.
"""

import tempfile
from pathlib import Path

import pytest

from app.security.sql_audit import (
    SQLAuditor,
    SQLAuditReport,
    SQLVulnerability,
    run_audit,
)


class TestSQLVulnerability:
    """Test the SQLVulnerability dataclass."""

    def test_create_vulnerability(self):
        """Test creating a vulnerability instance."""
        vuln = SQLVulnerability(
            file_path="test.py",
            line_number=10,
            code_snippet='query = f"SELECT * FROM {table}"',
            severity="high",
            description="f-string with SQL",
            recommendation="Use parameterized queries",
        )

        assert vuln.file_path == "test.py"
        assert vuln.line_number == 10
        assert "SELECT" in vuln.code_snippet
        assert vuln.severity == "high"


class TestSQLAuditReport:
    """Test the SQLAuditReport class."""

    def test_empty_report(self):
        """Test a report with no vulnerabilities."""
        report = SQLAuditReport()

        assert report.total_files_scanned == 0
        assert len(report.vulnerabilities) == 0
        assert not report.has_vulnerabilities
        assert report.high_severity_count == 0
        assert report.medium_severity_count == 0
        assert report.low_severity_count == 0

    def test_report_with_vulnerabilities(self):
        """Test a report with vulnerabilities of different severities."""
        report = SQLAuditReport()
        report.total_files_scanned = 3

        # Add high severity vulnerability
        report.vulnerabilities.append(
            SQLVulnerability(
                file_path="test1.py",
                line_number=5,
                code_snippet='f"SELECT * FROM {table}"',
                severity="high",
                description="f-string",
                recommendation="Fix it",
            )
        )

        # Add medium severity vulnerability
        report.vulnerabilities.append(
            SQLVulnerability(
                file_path="test2.py",
                line_number=10,
                code_snippet="query + var",
                severity="medium",
                description="concatenation",
                recommendation="Fix it",
            )
        )

        # Add low severity vulnerability
        report.vulnerabilities.append(
            SQLVulnerability(
                file_path="test3.py",
                line_number=15,
                code_snippet="some code",
                severity="low",
                description="minor issue",
                recommendation="Fix it",
            )
        )

        assert report.has_vulnerabilities
        assert report.high_severity_count == 1
        assert report.medium_severity_count == 1
        assert report.low_severity_count == 1
        assert len(report.vulnerabilities) == 3

    def test_generate_markdown_report_no_vulnerabilities(self):
        """Test markdown report generation with no vulnerabilities."""
        report = SQLAuditReport()
        report.total_files_scanned = 5
        report.safe_patterns_found = 10

        markdown = report.generate_markdown_report()

        assert "# SQL Parameterization Audit Report" in markdown
        assert "Files Scanned:** 5" in markdown
        assert "Vulnerabilities Found:** 0" in markdown
        assert "✅ Result: PASS" in markdown
        assert "Safe Patterns Detected:** 10" in markdown

    def test_generate_markdown_report_with_vulnerabilities(self):
        """Test markdown report generation with vulnerabilities."""
        report = SQLAuditReport()
        report.total_files_scanned = 3

        report.vulnerabilities.append(
            SQLVulnerability(
                file_path="vulnerable.py",
                line_number=42,
                code_snippet='query = f"SELECT * FROM {table}"',
                severity="high",
                description="f-string with SQL keyword",
                recommendation="Use parameterized queries",
            )
        )

        markdown = report.generate_markdown_report()

        assert "# SQL Parameterization Audit Report" in markdown
        assert "⚠️ Result: VULNERABILITIES DETECTED" in markdown
        assert "HIGH Severity Issues" in markdown
        assert "vulnerable.py:42" in markdown
        assert "f-string with SQL keyword" in markdown
        assert "Use parameterized queries" in markdown


class TestSQLAuditor:
    """Test the SQLAuditor class."""

    def test_detect_fstring_sql_injection(self):
        """Test detection of f-string SQL injection."""
        with tempfile.TemporaryDirectory() as tmpdir:
            # Create a file with vulnerable f-string
            test_file = Path(tmpdir) / "vulnerable.py"
            test_file.write_text(
                '''
def get_user(user_id):
    query = f"SELECT * FROM users WHERE id = {user_id}"
    return db.execute(query)
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            assert len(auditor.report.vulnerabilities) > 0
            assert any(
                v.severity == "high" for v in auditor.report.vulnerabilities
            )
            assert any(
                "f-string" in v.description.lower()
                for v in auditor.report.vulnerabilities
            )

    def test_detect_string_concatenation(self):
        """Test detection of string concatenation in SQL."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "vulnerable.py"
            test_file.write_text(
                '''
def search_products(name):
    query = "SELECT * FROM products WHERE name = '" + name + "'"
    return db.execute(query)
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            assert len(auditor.report.vulnerabilities) > 0
            assert any(
                v.severity == "high" for v in auditor.report.vulnerabilities
            )

    def test_detect_percent_formatting(self):
        """Test detection of % formatting in SQL."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "vulnerable.py"
            test_file.write_text(
                '''
def get_data(table_name):
    query = "SELECT * FROM %s" % table_name
    return db.execute(query)
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            assert len(auditor.report.vulnerabilities) > 0
            assert any(
                v.severity == "high" for v in auditor.report.vulnerabilities
            )

    def test_detect_format_method(self):
        """Test detection of .format() in SQL."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "vulnerable.py"
            test_file.write_text(
                '''
def update_record(value):
    query = "UPDATE users SET status = '{}'".format(value)
    return db.execute(query)
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            assert len(auditor.report.vulnerabilities) > 0

    def test_safe_sqlalchemy_orm_not_flagged(self):
        """Test that safe SQLAlchemy ORM queries are not flagged."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "safe.py"
            test_file.write_text(
                '''
from sqlalchemy import select
from app.models import User

def get_user(user_id):
    stmt = select(User).where(User.id == user_id)
    return db.execute(stmt)

def get_users_by_status(status):
    stmt = select(User).filter(User.status == status)
    return db.execute(stmt)
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            # Should not find vulnerabilities in safe code
            assert len(auditor.report.vulnerabilities) == 0
            # Should detect safe patterns
            assert auditor.report.safe_patterns_found > 0

    def test_safe_parameterized_queries_not_flagged(self):
        """Test that parameterized queries are not flagged."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "safe.py"
            test_file.write_text(
                '''
from sqlalchemy import text, bindparam

def get_user(user_id):
    query = text("SELECT * FROM users WHERE id = :user_id")
    return db.execute(query, {"user_id": user_id})

def search_users(name):
    query = text("SELECT * FROM users WHERE name = :name").bindparams(
        bindparam("name")
    )
    return db.execute(query, {"name": name})
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            # Should not find vulnerabilities
            assert len(auditor.report.vulnerabilities) == 0
            # Should detect safe patterns (bindparam)
            assert auditor.report.safe_patterns_found > 0

    def test_audit_directory(self):
        """Test auditing an entire directory."""
        with tempfile.TemporaryDirectory() as tmpdir:
            tmpdir_path = Path(tmpdir)

            # Create multiple files
            safe_file = tmpdir_path / "safe.py"
            safe_file.write_text(
                '''
from sqlalchemy import select
from app.models import User

def get_users():
    return select(User)
'''
            )

            vulnerable_file = tmpdir_path / "vulnerable.py"
            vulnerable_file.write_text(
                '''
def dangerous(user_input):
    query = f"SELECT * FROM users WHERE name = '{user_input}'"
    return query
'''
            )

            auditor = SQLAuditor()
            report = auditor.audit_directory(tmpdir)

            assert report.total_files_scanned == 2
            assert len(report.vulnerabilities) > 0
            assert report.safe_patterns_found > 0

    def test_skip_comments(self):
        """Test that comments are not flagged."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "commented.py"
            test_file.write_text(
                '''
def safe_function():
    # This is a comment: SELECT * FROM users
    # Don't use: f"SELECT * FROM {table}"
    return "safe"
'''
            )

            auditor = SQLAuditor()
            auditor.audit_file(test_file)

            # Comments should not be flagged
            assert len(auditor.report.vulnerabilities) == 0

    def test_skip_venv_and_cache_directories(self):
        """Test that virtual env and cache directories are skipped."""
        with tempfile.TemporaryDirectory() as tmpdir:
            tmpdir_path = Path(tmpdir)

            # Create directories that should be skipped
            (tmpdir_path / ".venv" / "lib").mkdir(parents=True)
            (tmpdir_path / "__pycache__").mkdir()

            # Create vulnerable files in skipped directories
            venv_file = tmpdir_path / ".venv" / "lib" / "bad.py"
            venv_file.write_text('f"SELECT * FROM {table}"')

            cache_file = tmpdir_path / "__pycache__" / "bad.py"
            cache_file.write_text('f"SELECT * FROM {table}"')

            # Create a safe file in the main directory
            safe_file = tmpdir_path / "safe.py"
            safe_file.write_text("print('hello')")

            auditor = SQLAuditor()
            report = auditor.audit_directory(str(tmpdir_path))

            # Should only scan the safe file, not venv or cache
            assert report.total_files_scanned == 1
            assert len(report.vulnerabilities) == 0

    def test_handle_syntax_errors(self):
        """Test that files with syntax errors are handled gracefully."""
        with tempfile.TemporaryDirectory() as tmpdir:
            test_file = Path(tmpdir) / "syntax_error.py"
            test_file.write_text(
                '''
def broken(:
    this is not valid python
'''
            )

            auditor = SQLAuditor()
            # Should not raise an exception
            auditor.audit_file(test_file)

            # File should still be counted
            assert auditor.report.total_files_scanned == 1


def test_run_audit_function():
    """Test the run_audit helper function."""
    with tempfile.TemporaryDirectory() as tmpdir:
        tmpdir_path = Path(tmpdir)

        # Create a test file
        test_file = tmpdir_path / "test.py"
        test_file.write_text(
            '''
from sqlalchemy import select
from app.models import User

def get_users():
    return select(User)
'''
        )

        report = run_audit(str(tmpdir_path))

        assert isinstance(report, SQLAuditReport)
        assert report.total_files_scanned >= 1


def test_execute_string_concatenation_detection():
    """Test detection of string concatenation in SQL queries."""
    with tempfile.TemporaryDirectory() as tmpdir:
        test_file = Path(tmpdir) / "vulnerable.py"
        test_file.write_text(
            '''
def bad_query(table, value):
    # This concatenation will be detected by regex pattern
    return db.execute("SELECT * FROM " + table + " WHERE value = " + str(value))
'''
        )

        auditor = SQLAuditor()
        auditor.audit_file(test_file)

        # The regex pattern should detect the concatenation
        assert len(auditor.report.vulnerabilities) > 0
        assert any(
            "concatenation" in v.description.lower()
            or "concatenation" in v.recommendation.lower()
            for v in auditor.report.vulnerabilities
        )


def test_multiple_sql_keywords():
    """Test detection with different SQL keywords."""
    with tempfile.TemporaryDirectory() as tmpdir:
        test_file = Path(tmpdir) / "vulnerable.py"
        test_file.write_text(
            '''
def bad_queries(value):
    insert = f"INSERT INTO users VALUES ({value})"
    update = f"UPDATE users SET name = '{value}'"
    delete = f"DELETE FROM users WHERE id = {value}"
    drop = f"DROP TABLE {value}"
    return [insert, update, delete, drop]
'''
        )

        auditor = SQLAuditor()
        auditor.audit_file(test_file)

        # Should detect all 4 vulnerable queries
        assert len(auditor.report.vulnerabilities) >= 4


def test_report_files_with_issues_tracking():
    """Test that files with issues are tracked in the report."""
    with tempfile.TemporaryDirectory() as tmpdir:
        tmpdir_path = Path(tmpdir)

        # Create vulnerable file
        vuln_file = tmpdir_path / "vulnerable.py"
        vuln_file.write_text('query = f"SELECT * FROM {table}"')

        # Create safe file
        safe_file = tmpdir_path / "safe.py"
        safe_file.write_text("print('hello')")

        auditor = SQLAuditor()
        report = auditor.audit_directory(str(tmpdir_path))

        # Only the vulnerable file should be in files_with_issues
        assert len(report.files_with_issues) == 1
        assert "vulnerable.py" in report.files_with_issues[0]
