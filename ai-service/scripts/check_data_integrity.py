#!/usr/bin/env python3
"""Data integrity verification script for LAYA AI Service.

This script performs comprehensive data integrity checks on the database to
ensure data quality, consistency, and compliance with business rules.

Checks include:
- Referential integrity (foreign key relationships)
- Data consistency (valid ranges, dates, enums)
- Business rule compliance
- Orphaned records detection
- Data completeness

Usage:
    python scripts/check_data_integrity.py                    # Basic checks
    python scripts/check_data_integrity.py --full             # Full verification
    python scripts/check_data_integrity.py --verbose          # Detailed output
    python scripts/check_data_integrity.py --fix-orphans      # Remove orphaned records
"""

import argparse
import asyncio
import sys
from datetime import datetime
from typing import Any, Dict, List, Optional, Tuple
from uuid import UUID

from sqlalchemy import select, func, and_, or_
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.database import AsyncSessionLocal, engine
from app.models import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
    CoachingRecommendation,
    CoachingSession,
    CommunicationPreference,
    EvidenceSource,
    HomeActivity,
    ParentReport,
)
from app.models.base import Base


# ANSI color codes for terminal output
class Colors:
    """ANSI color codes for colorized terminal output."""
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'


class IntegrityCheckResult:
    """Result of an integrity check operation."""

    def __init__(self, check_name: str):
        """Initialize check result.

        Args:
            check_name: Name of the integrity check
        """
        self.check_name = check_name
        self.passed = True
        self.issues: List[str] = []
        self.warnings: List[str] = []
        self.info: List[str] = []
        self.records_checked = 0
        self.issues_found = 0

    def add_issue(self, message: str) -> None:
        """Add an issue to the check result.

        Args:
            message: Issue description
        """
        self.issues.append(message)
        self.issues_found += 1
        self.passed = False

    def add_warning(self, message: str) -> None:
        """Add a warning to the check result.

        Args:
            message: Warning description
        """
        self.warnings.append(message)

    def add_info(self, message: str) -> None:
        """Add informational message to the check result.

        Args:
            message: Info description
        """
        self.info.append(message)

    def __str__(self) -> str:
        """String representation of check result."""
        status = f"{Colors.OKGREEN}✓ PASS{Colors.ENDC}" if self.passed else f"{Colors.FAIL}✗ FAIL{Colors.ENDC}"
        return f"{status} - {self.check_name}: {self.records_checked} records checked, {self.issues_found} issues found"


class DataIntegrityChecker:
    """Comprehensive data integrity verification."""

    def __init__(self, session: AsyncSession, verbose: bool = False, fix_orphans: bool = False):
        """Initialize integrity checker.

        Args:
            session: Database session
            verbose: Enable verbose output
            fix_orphans: Automatically fix orphaned records
        """
        self.session = session
        self.verbose = verbose
        self.fix_orphans = fix_orphans
        self.results: List[IntegrityCheckResult] = []

    async def run_all_checks(self, full: bool = False) -> bool:
        """Run all integrity checks.

        Args:
            full: Run full verification including expensive checks

        Returns:
            True if all checks passed, False otherwise
        """
        print(f"\n{Colors.HEADER}{Colors.BOLD}LAYA Data Integrity Verification{Colors.ENDC}")
        print(f"{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}\n")

        # Basic integrity checks
        await self._check_activity_integrity()
        await self._check_activity_participation_integrity()
        await self._check_activity_recommendation_integrity()
        await self._check_coaching_session_integrity()
        await self._check_coaching_recommendation_integrity()
        await self._check_evidence_source_integrity()
        await self._check_parent_report_integrity()
        await self._check_home_activity_integrity()
        await self._check_communication_preference_integrity()

        if full:
            # Extended checks
            await self._check_orphaned_records()
            await self._check_data_consistency()
            await self._check_business_rules()

        # Print summary
        self._print_summary()

        return all(result.passed for result in self.results)

    async def _check_activity_integrity(self) -> None:
        """Check Activity model data integrity."""
        result = IntegrityCheckResult("Activity Integrity")

        # Get all activities
        stmt = select(Activity)
        db_result = await self.session.execute(stmt)
        activities = db_result.scalars().all()
        result.records_checked = len(activities)

        for activity in activities:
            # Check required fields
            if not activity.name or not activity.name.strip():
                result.add_issue(f"Activity {activity.id}: Empty name")

            if not activity.description or not activity.description.strip():
                result.add_issue(f"Activity {activity.id}: Empty description")

            # Check age range validity
            if activity.min_age_months is not None and activity.max_age_months is not None:
                if activity.min_age_months < 0:
                    result.add_issue(f"Activity {activity.id}: Negative min_age_months ({activity.min_age_months})")
                if activity.max_age_months < 0:
                    result.add_issue(f"Activity {activity.id}: Negative max_age_months ({activity.max_age_months})")
                if activity.min_age_months > activity.max_age_months:
                    result.add_issue(f"Activity {activity.id}: min_age ({activity.min_age_months}) > max_age ({activity.max_age_months})")
                if activity.max_age_months > 216:  # 18 years max
                    result.add_warning(f"Activity {activity.id}: max_age ({activity.max_age_months} months) seems too high")

            # Check duration validity
            if activity.duration_minutes <= 0:
                result.add_issue(f"Activity {activity.id}: Invalid duration ({activity.duration_minutes} minutes)")
            if activity.duration_minutes > 240:  # 4 hours
                result.add_warning(f"Activity {activity.id}: Very long duration ({activity.duration_minutes} minutes)")

            # Check enum values
            if activity.activity_type not in ActivityType:
                result.add_issue(f"Activity {activity.id}: Invalid activity_type ({activity.activity_type})")
            if activity.difficulty not in ActivityDifficulty:
                result.add_issue(f"Activity {activity.id}: Invalid difficulty ({activity.difficulty})")

            # Check materials
            if not activity.materials_needed or len(activity.materials_needed) == 0:
                result.add_warning(f"Activity {activity.id}: No materials listed")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} activities")

        self.results.append(result)
        print(result)

    async def _check_activity_participation_integrity(self) -> None:
        """Check ActivityParticipation model data integrity."""
        result = IntegrityCheckResult("Activity Participation Integrity")

        # Get all participations
        stmt = select(ActivityParticipation)
        db_result = await self.session.execute(stmt)
        participations = db_result.scalars().all()
        result.records_checked = len(participations)

        # Get all activity IDs for reference validation
        activity_stmt = select(Activity.id)
        activity_result = await self.session.execute(activity_stmt)
        valid_activity_ids = {row[0] for row in activity_result.all()}

        for participation in participations:
            # Check foreign key references
            if participation.activity_id not in valid_activity_ids:
                result.add_issue(f"Participation {participation.id}: References non-existent activity {participation.activity_id}")

            # Check date logic
            if participation.completed_at and participation.started_at:
                if participation.completed_at < participation.started_at:
                    result.add_issue(f"Participation {participation.id}: completed_at before started_at")

                # Check if duration matches
                actual_duration = (participation.completed_at - participation.started_at).total_seconds() / 60
                if participation.duration_minutes and abs(actual_duration - participation.duration_minutes) > 5:
                    result.add_warning(f"Participation {participation.id}: Duration mismatch (recorded: {participation.duration_minutes}, actual: {actual_duration:.1f})")

            # Check completion status consistency
            if participation.completion_status == "completed" and not participation.completed_at:
                result.add_issue(f"Participation {participation.id}: Marked completed but no completed_at timestamp")

            if participation.completion_status != "completed" and participation.completed_at:
                result.add_issue(f"Participation {participation.id}: Has completed_at but status is '{participation.completion_status}'")

            # Check engagement score range
            if participation.engagement_score is not None:
                if not (0.0 <= participation.engagement_score <= 1.0):
                    result.add_issue(f"Participation {participation.id}: Invalid engagement_score ({participation.engagement_score})")

            # Check duration validity
            if participation.duration_minutes is not None and participation.duration_minutes <= 0:
                result.add_issue(f"Participation {participation.id}: Invalid duration ({participation.duration_minutes} minutes)")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} activity participations")
            result.add_info(f"Validated against {len(valid_activity_ids)} activities")

        self.results.append(result)
        print(result)

    async def _check_activity_recommendation_integrity(self) -> None:
        """Check ActivityRecommendation model data integrity."""
        result = IntegrityCheckResult("Activity Recommendation Integrity")

        # Get all recommendations
        stmt = select(ActivityRecommendation)
        db_result = await self.session.execute(stmt)
        recommendations = db_result.scalars().all()
        result.records_checked = len(recommendations)

        # Get all activity IDs for reference validation
        activity_stmt = select(Activity.id)
        activity_result = await self.session.execute(activity_stmt)
        valid_activity_ids = {row[0] for row in activity_result.all()}

        for recommendation in recommendations:
            # Check foreign key references
            if recommendation.activity_id not in valid_activity_ids:
                result.add_issue(f"Recommendation {recommendation.id}: References non-existent activity {recommendation.activity_id}")

            # Check relevance score
            if not (0.0 <= recommendation.relevance_score <= 1.0):
                result.add_issue(f"Recommendation {recommendation.id}: Invalid relevance_score ({recommendation.relevance_score})")

            # Check rationale
            if not recommendation.rationale or not recommendation.rationale.strip():
                result.add_warning(f"Recommendation {recommendation.id}: Empty rationale")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} activity recommendations")

        self.results.append(result)
        print(result)

    async def _check_coaching_session_integrity(self) -> None:
        """Check CoachingSession model data integrity."""
        result = IntegrityCheckResult("Coaching Session Integrity")

        # Get all sessions
        stmt = select(CoachingSession)
        db_result = await self.session.execute(stmt)
        sessions = db_result.scalars().all()
        result.records_checked = len(sessions)

        for session_obj in sessions:
            # Check required fields
            if not session_obj.question or not session_obj.question.strip():
                result.add_issue(f"Session {session_obj.id}: Empty question")

            # Check timestamps
            if session_obj.updated_at and session_obj.created_at:
                if session_obj.updated_at < session_obj.created_at:
                    result.add_issue(f"Session {session_obj.id}: updated_at before created_at")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} coaching sessions")

        self.results.append(result)
        print(result)

    async def _check_coaching_recommendation_integrity(self) -> None:
        """Check CoachingRecommendation model data integrity."""
        result = IntegrityCheckResult("Coaching Recommendation Integrity")

        # Get all coaching recommendations
        stmt = select(CoachingRecommendation)
        db_result = await self.session.execute(stmt)
        recommendations = db_result.scalars().all()
        result.records_checked = len(recommendations)

        # Get all session IDs for reference validation
        session_stmt = select(CoachingSession.id)
        session_result = await self.session.execute(session_stmt)
        valid_session_ids = {row[0] for row in session_result.all()}

        for recommendation in recommendations:
            # Check foreign key references
            if recommendation.session_id not in valid_session_ids:
                result.add_issue(f"Coaching Recommendation {recommendation.id}: References non-existent session {recommendation.session_id}")

            # Check required fields
            if not recommendation.title or not recommendation.title.strip():
                result.add_issue(f"Coaching Recommendation {recommendation.id}: Empty title")

            if not recommendation.content or not recommendation.content.strip():
                result.add_issue(f"Coaching Recommendation {recommendation.id}: Empty content")

            # Check relevance score
            if not (0.0 <= recommendation.relevance_score <= 1.0):
                result.add_issue(f"Coaching Recommendation {recommendation.id}: Invalid relevance_score ({recommendation.relevance_score})")

            # Check priority
            valid_priorities = ["low", "medium", "high", "urgent"]
            if recommendation.priority not in valid_priorities:
                result.add_issue(f"Coaching Recommendation {recommendation.id}: Invalid priority ({recommendation.priority})")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} coaching recommendations")

        self.results.append(result)
        print(result)

    async def _check_evidence_source_integrity(self) -> None:
        """Check EvidenceSource model data integrity."""
        result = IntegrityCheckResult("Evidence Source Integrity")

        # Get all evidence sources
        stmt = select(EvidenceSource)
        db_result = await self.session.execute(stmt)
        sources = db_result.scalars().all()
        result.records_checked = len(sources)

        # Get all recommendation IDs for reference validation
        rec_stmt = select(CoachingRecommendation.id)
        rec_result = await self.session.execute(rec_stmt)
        valid_recommendation_ids = {row[0] for row in rec_result.all()}

        for source in sources:
            # Check foreign key references
            if source.recommendation_id not in valid_recommendation_ids:
                result.add_issue(f"Evidence Source {source.id}: References non-existent recommendation {source.recommendation_id}")

            # Check required fields
            if not source.title or not source.title.strip():
                result.add_issue(f"Evidence Source {source.id}: Empty title")

            # Check relevance score
            if not (0.0 <= source.relevance_score <= 1.0):
                result.add_issue(f"Evidence Source {source.id}: Invalid relevance_score ({source.relevance_score})")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} evidence sources")

        self.results.append(result)
        print(result)

    async def _check_parent_report_integrity(self) -> None:
        """Check ParentReport model data integrity."""
        result = IntegrityCheckResult("Parent Report Integrity")

        # Get all parent reports
        stmt = select(ParentReport)
        db_result = await self.session.execute(stmt)
        reports = db_result.scalars().all()
        result.records_checked = len(reports)

        for report in reports:
            # Check required fields
            if not report.date:
                result.add_issue(f"Parent Report {report.id}: Missing date")

            # Check language codes
            valid_languages = ["en", "fr", "es", "ar"]  # Extend as needed
            if report.language and report.language not in valid_languages:
                result.add_warning(f"Parent Report {report.id}: Unusual language code ({report.language})")

            # Check date not in future
            if report.date and report.date > datetime.utcnow().date():
                result.add_warning(f"Parent Report {report.id}: Date in the future ({report.date})")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} parent reports")

        self.results.append(result)
        print(result)

    async def _check_home_activity_integrity(self) -> None:
        """Check HomeActivity model data integrity."""
        result = IntegrityCheckResult("Home Activity Integrity")

        # Get all home activities
        stmt = select(HomeActivity)
        db_result = await self.session.execute(stmt)
        activities = db_result.scalars().all()
        result.records_checked = len(activities)

        # Get all parent report IDs for reference validation
        report_stmt = select(ParentReport.id)
        report_result = await self.session.execute(report_stmt)
        valid_report_ids = {row[0] for row in report_result.all()}

        for activity in activities:
            # Check foreign key references
            if activity.parent_report_id not in valid_report_ids:
                result.add_issue(f"Home Activity {activity.id}: References non-existent report {activity.parent_report_id}")

            # Check required fields
            if not activity.title or not activity.title.strip():
                result.add_issue(f"Home Activity {activity.id}: Empty title")

            if not activity.description or not activity.description.strip():
                result.add_issue(f"Home Activity {activity.id}: Empty description")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} home activities")

        self.results.append(result)
        print(result)

    async def _check_communication_preference_integrity(self) -> None:
        """Check CommunicationPreference model data integrity."""
        result = IntegrityCheckResult("Communication Preference Integrity")

        # Get all preferences
        stmt = select(CommunicationPreference)
        db_result = await self.session.execute(stmt)
        preferences = db_result.scalars().all()
        result.records_checked = len(preferences)

        for pref in preferences:
            # Check at least one preference is set
            has_preference = (
                pref.email_enabled or
                pref.sms_enabled or
                pref.push_enabled or
                pref.in_app_enabled
            )
            if not has_preference:
                result.add_warning(f"Communication Preference {pref.id}: No communication channels enabled")

            # Check language
            valid_languages = ["en", "fr", "es", "ar"]
            if pref.language and pref.language not in valid_languages:
                result.add_warning(f"Communication Preference {pref.id}: Unusual language ({pref.language})")

        if self.verbose:
            result.add_info(f"Checked {result.records_checked} communication preferences")

        self.results.append(result)
        print(result)

    async def _check_orphaned_records(self) -> None:
        """Check for orphaned records (no parent)."""
        result = IntegrityCheckResult("Orphaned Records Check")

        orphans_found = 0

        # Check for orphaned activity participations
        stmt = select(ActivityParticipation).outerjoin(
            Activity, ActivityParticipation.activity_id == Activity.id
        ).where(Activity.id.is_(None))
        db_result = await self.session.execute(stmt)
        orphaned_participations = db_result.scalars().all()

        if orphaned_participations:
            orphans_found += len(orphaned_participations)
            result.add_issue(f"Found {len(orphaned_participations)} orphaned activity participations")
            if self.fix_orphans:
                for participation in orphaned_participations:
                    await self.session.delete(participation)
                await self.session.commit()
                result.add_info(f"Deleted {len(orphaned_participations)} orphaned participations")

        # Check for orphaned activity recommendations
        stmt = select(ActivityRecommendation).outerjoin(
            Activity, ActivityRecommendation.activity_id == Activity.id
        ).where(Activity.id.is_(None))
        db_result = await self.session.execute(stmt)
        orphaned_recommendations = db_result.scalars().all()

        if orphaned_recommendations:
            orphans_found += len(orphaned_recommendations)
            result.add_issue(f"Found {len(orphaned_recommendations)} orphaned activity recommendations")
            if self.fix_orphans:
                for recommendation in orphaned_recommendations:
                    await self.session.delete(recommendation)
                await self.session.commit()
                result.add_info(f"Deleted {len(orphaned_recommendations)} orphaned recommendations")

        # Check for orphaned coaching recommendations
        stmt = select(CoachingRecommendation).outerjoin(
            CoachingSession, CoachingRecommendation.session_id == CoachingSession.id
        ).where(CoachingSession.id.is_(None))
        db_result = await self.session.execute(stmt)
        orphaned_coaching_recs = db_result.scalars().all()

        if orphaned_coaching_recs:
            orphans_found += len(orphaned_coaching_recs)
            result.add_issue(f"Found {len(orphaned_coaching_recs)} orphaned coaching recommendations")
            if self.fix_orphans:
                for rec in orphaned_coaching_recs:
                    await self.session.delete(rec)
                await self.session.commit()
                result.add_info(f"Deleted {len(orphaned_coaching_recs)} orphaned coaching recommendations")

        # Check for orphaned evidence sources
        stmt = select(EvidenceSource).outerjoin(
            CoachingRecommendation, EvidenceSource.recommendation_id == CoachingRecommendation.id
        ).where(CoachingRecommendation.id.is_(None))
        db_result = await self.session.execute(stmt)
        orphaned_evidence = db_result.scalars().all()

        if orphaned_evidence:
            orphans_found += len(orphaned_evidence)
            result.add_issue(f"Found {len(orphaned_evidence)} orphaned evidence sources")
            if self.fix_orphans:
                for evidence in orphaned_evidence:
                    await self.session.delete(evidence)
                await self.session.commit()
                result.add_info(f"Deleted {len(orphaned_evidence)} orphaned evidence sources")

        # Check for orphaned home activities
        stmt = select(HomeActivity).outerjoin(
            ParentReport, HomeActivity.parent_report_id == ParentReport.id
        ).where(ParentReport.id.is_(None))
        db_result = await self.session.execute(stmt)
        orphaned_home_activities = db_result.scalars().all()

        if orphaned_home_activities:
            orphans_found += len(orphaned_home_activities)
            result.add_issue(f"Found {len(orphaned_home_activities)} orphaned home activities")
            if self.fix_orphans:
                for activity in orphaned_home_activities:
                    await self.session.delete(activity)
                await self.session.commit()
                result.add_info(f"Deleted {len(orphaned_home_activities)} orphaned home activities")

        result.records_checked = orphans_found
        if orphans_found == 0:
            result.add_info("No orphaned records found")

        self.results.append(result)
        print(result)

    async def _check_data_consistency(self) -> None:
        """Check data consistency across tables."""
        result = IntegrityCheckResult("Data Consistency Check")

        # Check activity participation completion rates
        stmt = select(func.count(ActivityParticipation.id))
        total_result = await self.session.execute(stmt)
        total_participations = total_result.scalar() or 0

        stmt = select(func.count(ActivityParticipation.id)).where(
            ActivityParticipation.completion_status == "completed"
        )
        completed_result = await self.session.execute(stmt)
        completed_participations = completed_result.scalar() or 0

        if total_participations > 0:
            completion_rate = (completed_participations / total_participations) * 100
            result.add_info(f"Activity completion rate: {completion_rate:.1f}%")
            if completion_rate < 50:
                result.add_warning(f"Low completion rate: {completion_rate:.1f}%")

        # Check coaching sessions have recommendations
        stmt = select(CoachingSession.id).outerjoin(
            CoachingRecommendation, CoachingSession.id == CoachingRecommendation.session_id
        ).group_by(CoachingSession.id).having(func.count(CoachingRecommendation.id) == 0)
        db_result = await self.session.execute(stmt)
        sessions_without_recs = db_result.scalars().all()

        if sessions_without_recs:
            result.add_warning(f"Found {len(sessions_without_recs)} coaching sessions without recommendations")

        # Check recommendations have evidence sources
        stmt = select(CoachingRecommendation.id).outerjoin(
            EvidenceSource, CoachingRecommendation.id == EvidenceSource.recommendation_id
        ).group_by(CoachingRecommendation.id).having(func.count(EvidenceSource.id) == 0)
        db_result = await self.session.execute(stmt)
        recs_without_evidence = db_result.scalars().all()

        if recs_without_evidence:
            result.add_warning(f"Found {len(recs_without_evidence)} recommendations without evidence sources")

        result.records_checked = total_participations
        self.results.append(result)
        print(result)

    async def _check_business_rules(self) -> None:
        """Check business rule compliance."""
        result = IntegrityCheckResult("Business Rules Check")

        # Check activity age ranges align with difficulty
        stmt = select(Activity).where(
            and_(
                Activity.difficulty == ActivityDifficulty.EASY,
                Activity.min_age_months > 36  # Easy activities shouldn't be for 3+ years
            )
        )
        db_result = await self.session.execute(stmt)
        misaligned = db_result.scalars().all()

        if misaligned:
            result.add_warning(f"Found {len(misaligned)} easy activities with high min_age")

        # Check activity durations are reasonable
        stmt = select(Activity).where(
            or_(
                Activity.duration_minutes < 5,  # Too short
                Activity.duration_minutes > 180  # Too long (3+ hours)
            )
        )
        db_result = await self.session.execute(stmt)
        unusual_durations = db_result.scalars().all()

        if unusual_durations:
            result.add_warning(f"Found {len(unusual_durations)} activities with unusual durations")

        # Check engagement scores
        stmt = select(ActivityParticipation).where(
            and_(
                ActivityParticipation.engagement_score.isnot(None),
                ActivityParticipation.engagement_score < 0.3  # Low engagement
            )
        )
        db_result = await self.session.execute(stmt)
        low_engagement = db_result.scalars().all()

        if low_engagement:
            result.add_info(f"Found {len(low_engagement)} participations with low engagement (<0.3)")

        result.records_checked = len(misaligned) + len(unusual_durations) + len(low_engagement)
        self.results.append(result)
        print(result)

    def _print_summary(self) -> None:
        """Print verification summary."""
        print(f"\n{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}")
        print(f"{Colors.HEADER}{Colors.BOLD}Verification Summary{Colors.ENDC}\n")

        total_checks = len(self.results)
        passed_checks = sum(1 for r in self.results if r.passed)
        failed_checks = total_checks - passed_checks
        total_issues = sum(r.issues_found for r in self.results)
        total_warnings = sum(len(r.warnings) for r in self.results)

        print(f"Total Checks: {total_checks}")
        print(f"{Colors.OKGREEN}Passed: {passed_checks}{Colors.ENDC}")
        if failed_checks > 0:
            print(f"{Colors.FAIL}Failed: {failed_checks}{Colors.ENDC}")
        print(f"{Colors.FAIL}Issues Found: {total_issues}{Colors.ENDC}")
        print(f"{Colors.WARNING}Warnings: {total_warnings}{Colors.ENDC}")

        # Print detailed issues
        if total_issues > 0 and self.verbose:
            print(f"\n{Colors.FAIL}{Colors.BOLD}Issues:{Colors.ENDC}")
            for result in self.results:
                if result.issues:
                    print(f"\n{result.check_name}:")
                    for issue in result.issues:
                        print(f"  - {issue}")

        # Print warnings
        if total_warnings > 0 and self.verbose:
            print(f"\n{Colors.WARNING}{Colors.BOLD}Warnings:{Colors.ENDC}")
            for result in self.results:
                if result.warnings:
                    print(f"\n{result.check_name}:")
                    for warning in result.warnings:
                        print(f"  - {warning}")

        print(f"\n{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}")

        if passed_checks == total_checks:
            print(f"{Colors.OKGREEN}{Colors.BOLD}✓ All integrity checks passed!{Colors.ENDC}\n")
        else:
            print(f"{Colors.FAIL}{Colors.BOLD}✗ Some integrity checks failed. Review issues above.{Colors.ENDC}\n")


async def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="LAYA Data Integrity Verification",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python scripts/check_data_integrity.py                    # Basic checks
  python scripts/check_data_integrity.py --full             # Full verification
  python scripts/check_data_integrity.py --verbose          # Detailed output
  python scripts/check_data_integrity.py --fix-orphans      # Remove orphaned records
        """
    )
    parser.add_argument(
        "--full",
        action="store_true",
        help="Run full verification including extended checks"
    )
    parser.add_argument(
        "--verbose",
        action="store_true",
        help="Enable verbose output with detailed information"
    )
    parser.add_argument(
        "--fix-orphans",
        action="store_true",
        help="Automatically remove orphaned records"
    )

    args = parser.parse_args()

    # Create database session
    async with AsyncSessionLocal() as session:
        checker = DataIntegrityChecker(session, verbose=args.verbose, fix_orphans=args.fix_orphans)
        success = await checker.run_all_checks(full=args.full)

    # Exit with appropriate code
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    asyncio.run(main())
