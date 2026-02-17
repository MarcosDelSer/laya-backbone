"""SQL Parameterization Audit Tool for LAYA AI Service.

This module provides tools to audit Python code for SQL injection vulnerabilities
by detecting unsafe SQL query patterns and ensuring proper parameterization.

The auditor scans for:
- Raw SQL strings with string concatenation or formatting
- f-strings used in SQL queries
- .execute() calls with non-parameterized queries
- Direct string interpolation in SQL queries

Safe patterns (will not flag):
- SQLAlchemy ORM queries using select(), where(), etc.
- Parameterized queries with bind parameters
- SQLAlchemy text() with bound parameters
"""

import ast
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


@dataclass
class SQLVulnerability:
    """Represents a potential SQL injection vulnerability.

    Attributes:
        file_path: Path to the file containing the vulnerability.
        line_number: Line number where the vulnerability was found.
        code_snippet: The actual code that triggered the finding.
        severity: Severity level (high, medium, low).
        description: Description of the vulnerability.
        recommendation: Recommendation to fix the issue.
    """

    file_path: str
    line_number: int
    code_snippet: str
    severity: str
    description: str
    recommendation: str


@dataclass
class SQLAuditReport:
    """Contains the results of a SQL parameterization audit.

    Attributes:
        total_files_scanned: Total number of Python files scanned.
        vulnerabilities: List of detected vulnerabilities.
        safe_patterns_found: Count of safe parameterized patterns found.
        files_with_issues: List of files that have vulnerabilities.
    """

    total_files_scanned: int = 0
    vulnerabilities: list[SQLVulnerability] = field(default_factory=list)
    safe_patterns_found: int = 0
    files_with_issues: list[str] = field(default_factory=list)

    @property
    def has_vulnerabilities(self) -> bool:
        """Check if any vulnerabilities were found."""
        return len(self.vulnerabilities) > 0

    @property
    def high_severity_count(self) -> int:
        """Count of high severity vulnerabilities."""
        return sum(1 for v in self.vulnerabilities if v.severity == "high")

    @property
    def medium_severity_count(self) -> int:
        """Count of medium severity vulnerabilities."""
        return sum(1 for v in self.vulnerabilities if v.severity == "medium")

    @property
    def low_severity_count(self) -> int:
        """Count of low severity vulnerabilities."""
        return sum(1 for v in self.vulnerabilities if v.severity == "low")

    def generate_markdown_report(self) -> str:
        """Generate a markdown-formatted audit report.

        Returns:
            Markdown string containing the full audit report.
        """
        lines = [
            "# SQL Parameterization Audit Report",
            "",
            "## Executive Summary",
            "",
            f"- **Files Scanned:** {self.total_files_scanned}",
            f"- **Vulnerabilities Found:** {len(self.vulnerabilities)}",
            f"- **High Severity:** {self.high_severity_count}",
            f"- **Medium Severity:** {self.medium_severity_count}",
            f"- **Low Severity:** {self.low_severity_count}",
            f"- **Safe Patterns Detected:** {self.safe_patterns_found}",
            "",
        ]

        if not self.has_vulnerabilities:
            lines.extend([
                "## ✅ Result: PASS",
                "",
                "No SQL injection vulnerabilities detected. All SQL queries use proper parameterization.",
                "",
            ])
        else:
            lines.extend([
                "## ⚠️ Result: VULNERABILITIES DETECTED",
                "",
                "The following potential SQL injection vulnerabilities were found:",
                "",
            ])

            # Group vulnerabilities by severity
            for severity in ["high", "medium", "low"]:
                severity_vulns = [v for v in self.vulnerabilities if v.severity == severity]
                if severity_vulns:
                    lines.extend([
                        f"### {severity.upper()} Severity Issues",
                        "",
                    ])
                    for v in severity_vulns:
                        lines.extend([
                            f"#### {v.file_path}:{v.line_number}",
                            "",
                            f"**Description:** {v.description}",
                            "",
                            f"**Code:**",
                            "```python",
                            v.code_snippet,
                            "```",
                            "",
                            f"**Recommendation:** {v.recommendation}",
                            "",
                        ])

        lines.extend([
            "## Audit Methodology",
            "",
            "This audit scanned Python source files for the following unsafe patterns:",
            "",
            "1. **String concatenation in SQL queries** - `\"SELECT * FROM \" + table_name`",
            "2. **String formatting in SQL queries** - `\"SELECT * FROM %s\" % table_name`",
            "3. **f-strings in SQL queries** - `f\"SELECT * FROM {table_name}\"`",
            "4. **Direct .execute() with non-parameterized queries**",
            "5. **SQL keywords followed by string interpolation**",
            "",
            "### Safe Patterns (Not Flagged)",
            "",
            "- SQLAlchemy ORM queries using `select()`, `where()`, etc.",
            "- Parameterized queries with bind parameters",
            "- SQLAlchemy `text()` with bound parameters",
            "- Column names and table names from ORM models",
            "",
        ])

        return "\n".join(lines)


class SQLAuditor:
    """Audits Python code for SQL injection vulnerabilities.

    This class scans Python source files to detect unsafe SQL query patterns
    that could lead to SQL injection vulnerabilities. It uses both AST parsing
    and regex pattern matching to identify potential issues.

    Example:
        auditor = SQLAuditor()
        report = auditor.audit_directory("./app")
        if report.has_vulnerabilities:
            print(report.generate_markdown_report())
    """

    # Regex patterns for unsafe SQL practices
    SQL_KEYWORDS = r"\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\b"

    # Patterns that indicate potential SQL injection
    UNSAFE_PATTERNS = [
        # f-strings with SQL keywords
        (r'f["\'].*' + SQL_KEYWORDS + r'.*\{.*\}.*["\']', "high",
         "f-string with SQL keyword and variable interpolation",
         "Use parameterized queries with SQLAlchemy or text() with bind parameters"),

        # String concatenation with SQL
        (r'["\'].*' + SQL_KEYWORDS + r'.*["\'].*\+.*["\']', "high",
         "String concatenation in SQL query",
         "Use parameterized queries instead of concatenating strings"),

        # % formatting with SQL
        (r'["\'].*' + SQL_KEYWORDS + r'.*%s.*["\'].*%', "high",
         "String formatting (%) in SQL query",
         "Use parameterized queries with bind parameters"),

        # .format() with SQL
        (r'["\'].*' + SQL_KEYWORDS + r'.*\{.*\}.*["\']\.format\(', "high",
         ".format() used in SQL query",
         "Use parameterized queries with bind parameters"),
    ]

    def __init__(self) -> None:
        """Initialize the SQL auditor."""
        self.report = SQLAuditReport()

    def audit_file(self, file_path: Path) -> None:
        """Audit a single Python file for SQL injection vulnerabilities.

        Args:
            file_path: Path to the Python file to audit.
        """
        try:
            with open(file_path, "r", encoding="utf-8") as f:
                content = f.read()
                lines = content.split("\n")

            # Track safe patterns
            self._detect_safe_patterns(content)

            # Regex-based detection
            self._audit_with_regex(file_path, lines)

            # AST-based detection
            try:
                tree = ast.parse(content)
                self._audit_with_ast(file_path, tree, lines)
            except SyntaxError:
                # Skip files with syntax errors
                pass

            self.report.total_files_scanned += 1

        except Exception:
            # Skip files that can't be read
            pass

    def audit_directory(self, directory: str) -> SQLAuditReport:
        """Audit all Python files in a directory recursively.

        Args:
            directory: Path to the directory to audit.

        Returns:
            SQLAuditReport containing all findings.
        """
        directory_path = Path(directory)

        # Find all Python files, excluding virtual environments and test files
        python_files = []
        for pattern in ["**/*.py"]:
            for file_path in directory_path.glob(pattern):
                # Skip virtual environments, migrations, and other non-source files
                path_str = str(file_path)
                if any(exclude in path_str for exclude in [
                    ".venv", "venv", "__pycache__", ".pytest_cache",
                    "site-packages", "alembic/versions"
                ]):
                    continue
                python_files.append(file_path)

        # Audit each file
        for file_path in python_files:
            self.audit_file(file_path)

        return self.report

    def _detect_safe_patterns(self, content: str) -> None:
        """Detect and count safe parameterization patterns.

        Args:
            content: Source code content.
        """
        # Count SQLAlchemy select() usage (safe ORM pattern)
        safe_patterns = [
            r"select\([A-Z][a-zA-Z]*\)",  # select(Model)
            r"\.where\(",  # .where() clause
            r"\.filter\(",  # .filter() clause
            r"bindparam\(",  # Bind parameters
            r"text\(.*bindparam",  # text() with bindparam
        ]

        for pattern in safe_patterns:
            self.report.safe_patterns_found += len(re.findall(pattern, content))

    def _audit_with_regex(self, file_path: Path, lines: list[str]) -> None:
        """Audit code using regex pattern matching.

        Args:
            file_path: Path to the file being audited.
            lines: List of code lines.
        """
        for line_num, line in enumerate(lines, start=1):
            # Skip comments
            if line.strip().startswith("#"):
                continue

            for pattern, severity, description, recommendation in self.UNSAFE_PATTERNS:
                if re.search(pattern, line, re.IGNORECASE):
                    self._add_vulnerability(
                        file_path=str(file_path),
                        line_number=line_num,
                        code_snippet=line.strip(),
                        severity=severity,
                        description=description,
                        recommendation=recommendation,
                    )

    def _audit_with_ast(
        self, file_path: Path, tree: ast.AST, lines: list[str]
    ) -> None:
        """Audit code using AST parsing.

        Args:
            file_path: Path to the file being audited.
            tree: AST tree of the source code.
            lines: List of code lines.
        """
        for node in ast.walk(tree):
            # Check for f-strings (JoinedStr) containing SQL keywords
            if isinstance(node, ast.JoinedStr):
                self._check_fstring_node(file_path, node, lines)

            # Check for execute() calls with string arguments
            elif isinstance(node, ast.Call):
                self._check_execute_call(file_path, node, lines)

    def _check_fstring_node(
        self, file_path: Path, node: ast.JoinedStr, lines: list[str]
    ) -> None:
        """Check f-string nodes for SQL keywords.

        Args:
            file_path: Path to the file being audited.
            node: AST node representing an f-string.
            lines: List of code lines.
        """
        # Get the source code for this f-string
        if hasattr(node, "lineno"):
            line_num = node.lineno
            if line_num <= len(lines):
                line = lines[line_num - 1]

                # Check if this f-string contains SQL keywords
                if re.search(self.SQL_KEYWORDS, line, re.IGNORECASE):
                    # Check if it also has variable interpolation
                    if any(isinstance(v, ast.FormattedValue) for v in node.values):
                        self._add_vulnerability(
                            file_path=str(file_path),
                            line_number=line_num,
                            code_snippet=line.strip(),
                            severity="high",
                            description="f-string with SQL keyword and variable interpolation",
                            recommendation="Use parameterized queries with SQLAlchemy or text() with bind parameters",
                        )

    def _check_execute_call(
        self, file_path: Path, node: ast.Call, lines: list[str]
    ) -> None:
        """Check execute() calls for unsafe patterns.

        Args:
            file_path: Path to the file being audited.
            node: AST node representing a function call.
            lines: List of code lines.
        """
        # Check if this is an execute() call
        if isinstance(node.func, ast.Attribute) and node.func.attr == "execute":
            if hasattr(node, "lineno") and node.lineno <= len(lines):
                line = lines[node.lineno - 1]

                # Check for string concatenation or formatting in execute() args
                if node.args:
                    arg = node.args[0]

                    # Check for BinOp (concatenation with +)
                    if isinstance(arg, ast.BinOp) and isinstance(arg.op, ast.Add):
                        self._add_vulnerability(
                            file_path=str(file_path),
                            line_number=node.lineno,
                            code_snippet=line.strip(),
                            severity="high",
                            description="String concatenation in execute() call",
                            recommendation="Use parameterized queries with bind parameters",
                        )

    def _add_vulnerability(
        self,
        file_path: str,
        line_number: int,
        code_snippet: str,
        severity: str,
        description: str,
        recommendation: str,
    ) -> None:
        """Add a vulnerability to the report.

        Args:
            file_path: Path to the file with the vulnerability.
            line_number: Line number of the vulnerability.
            code_snippet: Code snippet containing the vulnerability.
            severity: Severity level (high, medium, low).
            description: Description of the vulnerability.
            recommendation: How to fix the vulnerability.
        """
        vulnerability = SQLVulnerability(
            file_path=file_path,
            line_number=line_number,
            code_snippet=code_snippet,
            severity=severity,
            description=description,
            recommendation=recommendation,
        )
        self.report.vulnerabilities.append(vulnerability)

        if file_path not in self.report.files_with_issues:
            self.report.files_with_issues.append(file_path)


def run_audit(directory: str = "./app") -> SQLAuditReport:
    """Run SQL parameterization audit on a directory.

    Args:
        directory: Directory to audit (default: "./app").

    Returns:
        SQLAuditReport with all findings.

    Example:
        report = run_audit("./app")
        print(report.generate_markdown_report())
    """
    auditor = SQLAuditor()
    return auditor.audit_directory(directory)


if __name__ == "__main__":
    # Run audit when executed directly
    import sys

    directory = sys.argv[1] if len(sys.argv) > 1 else "./app"
    report = run_audit(directory)

    print(report.generate_markdown_report())

    # Exit with error code if vulnerabilities found
    sys.exit(1 if report.has_vulnerabilities else 0)
