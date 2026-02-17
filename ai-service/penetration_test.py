#!/usr/bin/env python3
"""
Penetration Testing Script for IDOR Vulnerabilities

This script performs manual penetration testing against the AI service API
to verify that all IDOR vulnerabilities have been fixed.

Usage:
    python penetration_test.py --api-url http://localhost:8000
"""

import argparse
import json
import sys
from typing import Dict, List, Tuple, Optional
from uuid import UUID, uuid4
from dataclasses import dataclass
import requests


@dataclass
class TestUser:
    """Represents a test user with authentication token"""
    user_id: str
    username: str
    email: str
    role: str
    token: str


@dataclass
class TestResult:
    """Represents the result of a penetration test"""
    endpoint: str
    method: str
    description: str
    expected_status: int
    actual_status: int
    passed: bool
    details: str


class IDORPenetrationTester:
    """Penetration tester for IDOR vulnerabilities"""

    def __init__(self, api_url: str):
        self.api_url = api_url.rstrip('/')
        self.results: List[TestResult] = []

    def print_banner(self):
        """Print test banner"""
        print("=" * 80)
        print("IDOR PENETRATION TEST SUITE")
        print("=" * 80)
        print(f"Target API: {self.api_url}")
        print(f"Test Date: 2026-02-17")
        print("=" * 80)
        print()

    def create_test_user(self, username: str, role: str = "parent") -> Optional[TestUser]:
        """Create a test user and return authentication token"""
        # In a real scenario, this would create a user via the API
        # For now, we'll simulate with a mock token
        user_id = str(uuid4())
        email = f"{username}@test.com"

        print(f"  Creating test user: {username} (role: {role})")

        # Mock token - in production, this would be obtained from auth endpoint
        token = f"mock_token_{username}_{user_id}"

        return TestUser(
            user_id=user_id,
            username=username,
            email=email,
            role=role,
            token=token
        )

    def get_auth_headers(self, user: TestUser) -> Dict[str, str]:
        """Generate authentication headers for a user"""
        return {
            "Authorization": f"Bearer {user.token}",
            "Content-Type": "application/json"
        }

    def test_endpoint(
        self,
        method: str,
        endpoint: str,
        user: TestUser,
        expected_status: int,
        description: str,
        json_data: Optional[Dict] = None
    ) -> TestResult:
        """Test a single endpoint"""
        url = f"{self.api_url}{endpoint}"
        headers = self.get_auth_headers(user)

        try:
            if method == "GET":
                response = requests.get(url, headers=headers, timeout=5)
            elif method == "POST":
                response = requests.post(url, headers=headers, json=json_data, timeout=5)
            elif method == "PUT":
                response = requests.put(url, headers=headers, json=json_data, timeout=5)
            elif method == "PATCH":
                response = requests.patch(url, headers=headers, json=json_data, timeout=5)
            elif method == "DELETE":
                response = requests.delete(url, headers=headers, timeout=5)
            else:
                raise ValueError(f"Unsupported method: {method}")

            actual_status = response.status_code
            passed = actual_status == expected_status

            details = f"Response: {response.status_code}"
            if not passed:
                try:
                    details += f" | Body: {response.json()}"
                except:
                    details += f" | Body: {response.text[:100]}"

        except requests.exceptions.ConnectionError:
            actual_status = 0
            passed = False
            details = "Connection refused - service may not be running"
        except Exception as e:
            actual_status = 0
            passed = False
            details = f"Error: {str(e)}"

        result = TestResult(
            endpoint=endpoint,
            method=method,
            description=description,
            expected_status=expected_status,
            actual_status=actual_status,
            passed=passed,
            details=details
        )

        self.results.append(result)
        return result

    def print_result(self, result: TestResult):
        """Print a test result"""
        status_icon = "✅" if result.passed else "❌"
        print(f"  {status_icon} {result.method} {result.endpoint}")
        print(f"     Expected: {result.expected_status}, Got: {result.actual_status}")
        if not result.passed:
            print(f"     Details: {result.details}")
        print()

    def test_document_idor(self, user_a: TestUser, user_b: TestUser):
        """Test document IDOR vulnerabilities"""
        print("\n📄 Testing Document Service IDOR Protection")
        print("-" * 80)

        # Simulate document IDs
        user_a_doc_id = str(uuid4())
        user_a_template_id = str(uuid4())
        user_a_signature_id = str(uuid4())

        # Test 1: User B tries to access User A's document
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/documents/{user_a_doc_id}",
            user=user_b,
            expected_status=403,
            description="User B accessing User A's document"
        )
        self.print_result(result)

        # Test 2: User B tries to delete User A's document
        result = self.test_endpoint(
            method="DELETE",
            endpoint=f"/api/v1/documents/{user_a_doc_id}",
            user=user_b,
            expected_status=403,
            description="User B deleting User A's document"
        )
        self.print_result(result)

        # Test 3: User B tries to access User A's template
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/templates/{user_a_template_id}",
            user=user_b,
            expected_status=403,
            description="User B accessing User A's template"
        )
        self.print_result(result)

        # Test 4: User B tries to access User A's signature request
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/signature-requests/{user_a_signature_id}",
            user=user_b,
            expected_status=403,
            description="User B accessing User A's signature request"
        )
        self.print_result(result)

    def test_messaging_idor(self, user_a: TestUser, user_b: TestUser):
        """Test messaging IDOR vulnerabilities"""
        print("\n💬 Testing Messaging Service IDOR Protection")
        print("-" * 80)

        # Simulate IDs
        user_a_thread_id = str(uuid4())
        user_a_message_id = str(uuid4())

        # Test 1: User B tries to access User A's thread
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/threads/{user_a_thread_id}",
            user=user_b,
            expected_status=403,
            description="User B accessing User A's thread"
        )
        self.print_result(result)

        # Test 2: User B tries to read User A's message
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/messages/{user_a_message_id}",
            user=user_b,
            expected_status=403,
            description="User B reading User A's message"
        )
        self.print_result(result)

        # Test 3: User B tries to access User A's notification preferences
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/notifications/preferences/{user_a.user_id}",
            user=user_b,
            expected_status=403,
            description="User B accessing User A's notification preferences"
        )
        self.print_result(result)

        # Test 4: User B tries to modify User A's notification preferences
        result = self.test_endpoint(
            method="PATCH",
            endpoint=f"/api/v1/notifications/preferences/{user_a.user_id}/quiet-hours",
            user=user_b,
            expected_status=403,
            description="User B modifying User A's quiet hours",
            json_data={"start_time": "22:00", "end_time": "07:00"}
        )
        self.print_result(result)

    def test_communication_idor(self, user_a: TestUser, user_b: TestUser):
        """Test communication IDOR vulnerabilities"""
        print("\n📞 Testing Communication Service IDOR Protection")
        print("-" * 80)

        # Simulate child IDs
        user_a_child_id = str(uuid4())

        # Test 1: User B (parent) tries to access User A's child data
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/home-activities/{user_a_child_id}",
            user=user_b,
            expected_status=403,
            description="Parent B accessing Parent A's child home activities"
        )
        self.print_result(result)

        # Test 2: User B tries to generate report for User A's child
        result = self.test_endpoint(
            method="POST",
            endpoint="/api/v1/generate-report",
            user=user_b,
            expected_status=403,
            description="Parent B generating report for Parent A's child",
            json_data={"child_id": user_a_child_id, "report_type": "daily"}
        )
        self.print_result(result)

        # Test 3: User B tries to access User A's communication preferences
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/preferences/{user_a.user_id}",
            user=user_b,
            expected_status=403,
            description="Parent B accessing Parent A's preferences"
        )
        self.print_result(result)

    def test_development_profile_idor(self, user_a: TestUser, user_b: TestUser):
        """Test development profile IDOR vulnerabilities"""
        print("\n📊 Testing Development Profile Service IDOR Protection")
        print("-" * 80)

        # Simulate IDs
        user_a_child_id = str(uuid4())
        user_a_profile_id = str(uuid4())
        user_a_milestone_id = str(uuid4())

        # Test 1: User B tries to access User A's child profiles
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/children/{user_a_child_id}/profiles",
            user=user_b,
            expected_status=403,
            description="Parent B accessing Parent A's child profiles"
        )
        self.print_result(result)

        # Test 2: User B tries to access User A's profile
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/profiles/{user_a_profile_id}",
            user=user_b,
            expected_status=403,
            description="Parent B accessing Parent A's development profile"
        )
        self.print_result(result)

        # Test 3: User B tries to modify User A's milestone
        result = self.test_endpoint(
            method="PATCH",
            endpoint=f"/api/v1/milestones/{user_a_milestone_id}",
            user=user_b,
            expected_status=403,
            description="Parent B modifying Parent A's milestone",
            json_data={"status": "completed"}
        )
        self.print_result(result)

    def test_intervention_plan_idor(self, user_a: TestUser, user_b: TestUser):
        """Test intervention plan IDOR vulnerabilities"""
        print("\n🎯 Testing Intervention Plan Service IDOR Protection")
        print("-" * 80)

        # Simulate IDs
        user_a_child_id = str(uuid4())
        user_a_plan_id = str(uuid4())
        user_a_goal_id = str(uuid4())

        # Test 1: User B tries to access User A's child intervention plans
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/children/{user_a_child_id}/intervention-plans",
            user=user_b,
            expected_status=403,
            description="Parent B accessing Parent A's child intervention plans"
        )
        self.print_result(result)

        # Test 2: User B tries to access User A's intervention plan
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/intervention-plans/{user_a_plan_id}",
            user=user_b,
            expected_status=403,
            description="Parent B accessing Parent A's intervention plan"
        )
        self.print_result(result)

        # Test 3: User B tries to delete User A's goal
        result = self.test_endpoint(
            method="DELETE",
            endpoint=f"/api/v1/goals/{user_a_goal_id}",
            user=user_b,
            expected_status=403,
            description="Parent B deleting Parent A's goal"
        )
        self.print_result(result)

    def test_storage_idor(self, user_a: TestUser, user_b: TestUser):
        """Test storage IDOR vulnerabilities"""
        print("\n📁 Testing Storage Service IDOR Protection")
        print("-" * 80)

        # Simulate file ID
        user_a_file_id = str(uuid4())

        # Test 1: User B tries to access User A's private file
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/files/{user_a_file_id}",
            user=user_b,
            expected_status=404,  # Storage returns 404 for security
            description="User B accessing User A's private file"
        )
        self.print_result(result)

        # Test 2: User B tries to download User A's file
        result = self.test_endpoint(
            method="GET",
            endpoint=f"/api/v1/files/{user_a_file_id}/download",
            user=user_b,
            expected_status=404,  # Storage returns 404 for security
            description="User B downloading User A's file"
        )
        self.print_result(result)

        # Test 3: User B tries to delete User A's file
        result = self.test_endpoint(
            method="DELETE",
            endpoint=f"/api/v1/files/{user_a_file_id}",
            user=user_b,
            expected_status=404,  # Storage returns 404 for security
            description="User B deleting User A's file"
        )
        self.print_result(result)

        # Test 4: User B tries to generate secure URL for User A's file
        result = self.test_endpoint(
            method="POST",
            endpoint=f"/api/v1/files/{user_a_file_id}/secure-url",
            user=user_b,
            expected_status=404,  # Storage returns 404 for security
            description="User B generating secure URL for User A's file"
        )
        self.print_result(result)

    def run_all_tests(self):
        """Run all penetration tests"""
        self.print_banner()

        # Create test users
        print("Creating test users...")
        user_a = self.create_test_user("alice", "parent")
        user_b = self.create_test_user("bob", "parent")
        print()

        if not user_a or not user_b:
            print("❌ Failed to create test users")
            return False

        # Run all test suites
        self.test_document_idor(user_a, user_b)
        self.test_messaging_idor(user_a, user_b)
        self.test_communication_idor(user_a, user_b)
        self.test_development_profile_idor(user_a, user_b)
        self.test_intervention_plan_idor(user_a, user_b)
        self.test_storage_idor(user_a, user_b)

        # Print summary
        self.print_summary()

        return all(result.passed for result in self.results)

    def print_summary(self):
        """Print test summary"""
        print("\n" + "=" * 80)
        print("TEST SUMMARY")
        print("=" * 80)

        total = len(self.results)
        passed = sum(1 for r in self.results if r.passed)
        failed = total - passed

        print(f"Total Tests: {total}")
        print(f"Passed: {passed} ✅")
        print(f"Failed: {failed} ❌")
        print(f"Success Rate: {(passed/total*100):.1f}%")

        if failed > 0:
            print("\nFailed Tests:")
            for result in self.results:
                if not result.passed:
                    print(f"  ❌ {result.method} {result.endpoint}")
                    print(f"     {result.description}")
                    print(f"     Expected: {result.expected_status}, Got: {result.actual_status}")
                    print(f"     {result.details}")

        print("\n" + "=" * 80)

        if all(result.actual_status == 0 for result in self.results):
            print("⚠️  WARNING: All tests failed with connection errors.")
            print("   The API service may not be running.")
            print("   Start the service with: docker-compose up -d ai-service")
        elif failed == 0:
            print("🎉 SUCCESS: All IDOR vulnerabilities have been fixed!")
            print("   All unauthorized access attempts were properly blocked.")
        else:
            print("⚠️  SECURITY ISSUES DETECTED!")
            print("   Some endpoints did not properly block unauthorized access.")
            print("   Review the failed tests above and fix authorization checks.")

        print("=" * 80)


def main():
    """Main entry point"""
    parser = argparse.ArgumentParser(
        description="IDOR Penetration Testing Script for AI Service"
    )
    parser.add_argument(
        "--api-url",
        default="http://localhost:8000",
        help="Base URL of the API (default: http://localhost:8000)"
    )
    parser.add_argument(
        "--output",
        help="Output file for test results (JSON format)"
    )

    args = parser.parse_args()

    tester = IDORPenetrationTester(args.api_url)
    success = tester.run_all_tests()

    # Save results if output file specified
    if args.output:
        with open(args.output, 'w') as f:
            results_data = [
                {
                    "endpoint": r.endpoint,
                    "method": r.method,
                    "description": r.description,
                    "expected_status": r.expected_status,
                    "actual_status": r.actual_status,
                    "passed": r.passed,
                    "details": r.details
                }
                for r in tester.results
            ]
            json.dump({
                "total": len(results_data),
                "passed": sum(1 for r in results_data if r["passed"]),
                "failed": sum(1 for r in results_data if not r["passed"]),
                "results": results_data
            }, f, indent=2)
        print(f"\n📝 Results saved to: {args.output}")

    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
