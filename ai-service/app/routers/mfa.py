"""MFA router for LAYA AI Service.

Provides API endpoints for multi-factor authentication management.
Supports TOTP setup, verification, backup codes, and IP whitelist.
All endpoints require JWT authentication.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, Request
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.mfa import (
    BackupCodeStatusResponse,
    BackupCodesGenerateRequest,
    BackupCodesResponse,
    BackupCodeVerifyRequest,
    IPWhitelistCheckRequest,
    IPWhitelistCheckResponse,
    IPWhitelistCreateRequest,
    IPWhitelistEntryResponse,
    IPWhitelistListResponse,
    MFADisableRequest,
    MFAEnableRequest,
    MFASetupRequest,
    MFASetupResponse,
    MFAStatusResponse,
    MFAVerifyRequest,
    MFAVerifyResponse,
)
from app.services.mfa_service import (
    InvalidCodeError,
    MFAAlreadyEnabledError,
    MFALockoutError,
    MFANotEnabledError,
    MFAService,
)

router = APIRouter(prefix="/api/v1/mfa", tags=["mfa"])


# =============================================================================
# MFA Status and Setup Endpoints
# =============================================================================


@router.get(
    "/status",
    response_model=MFAStatusResponse,
    summary="Get MFA status",
    description="Get the current MFA configuration status for the authenticated user.",
)
async def get_mfa_status(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFAStatusResponse:
    """Get MFA status for the authenticated user.

    Returns information about whether MFA is enabled, the configured method,
    backup code availability, IP whitelist count, and lockout status.

    Args:
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFAStatusResponse with current MFA configuration state.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)
    return await service.get_mfa_status(user_id)


@router.post(
    "/setup",
    response_model=MFASetupResponse,
    summary="Initiate MFA setup",
    description="Start the MFA setup process by generating a TOTP secret and QR code.",
)
async def initiate_mfa_setup(
    request: MFASetupRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFASetupResponse:
    """Initiate MFA setup for the authenticated user.

    Generates a new TOTP secret and returns the information needed for the
    user to configure their authenticator app (e.g., Google Authenticator, Authy).

    The user must verify a TOTP code using the /enable endpoint to complete setup.

    Args:
        request: MFA setup request with method and optional recovery email.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFASetupResponse with secret and QR code URI.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is already enabled.
    """
    user_id = _get_user_id(current_user)
    user_email = _get_user_email(current_user)
    service = MFAService(db)

    try:
        return await service.initiate_setup(
            user_id=user_id,
            user_email=user_email,
            method=request.method,
            recovery_email=request.recovery_email,
        )
    except MFAAlreadyEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))


# =============================================================================
# MFA Verification and Enable/Disable Endpoints
# =============================================================================


@router.post(
    "/verify",
    response_model=MFAVerifyResponse,
    summary="Verify TOTP code",
    description="Verify a TOTP code from the user's authenticator app.",
)
async def verify_totp_code(
    request: MFAVerifyRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFAVerifyResponse:
    """Verify a TOTP code for the authenticated user.

    Used during login to complete MFA verification or to verify identity
    for sensitive operations.

    Args:
        request: Verification request with the TOTP code.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFAVerifyResponse with verification result.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled or setup not initiated.
        HTTPException: 423 if account is locked due to too many failed attempts.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.verify_totp(
            user_id=user_id,
            code=request.code,
            is_setup_verification=request.is_setup_verification,
        )
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except MFALockoutError as e:
        raise HTTPException(
            status_code=423,
            detail=f"Account locked due to too many failed attempts. Try again after {e.locked_until.isoformat()}",
        )


@router.post(
    "/enable",
    response_model=MFAVerifyResponse,
    summary="Enable MFA",
    description="Enable MFA after verifying a TOTP code during setup.",
)
async def enable_mfa(
    request: MFAEnableRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFAVerifyResponse:
    """Enable MFA for the authenticated user.

    Completes the MFA setup process by verifying the user can generate
    valid TOTP codes. Must be called after /setup.

    Args:
        request: Enable request with the TOTP code to verify.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFAVerifyResponse with result of enabling MFA.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA setup not initiated or already enabled.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.enable_mfa(user_id=user_id, code=request.code)
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except MFAAlreadyEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post(
    "/disable",
    response_model=MFAVerifyResponse,
    summary="Disable MFA",
    description="Disable MFA after verifying identity with a TOTP or backup code.",
)
async def disable_mfa(
    request: MFADisableRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFAVerifyResponse:
    """Disable MFA for the authenticated user.

    Requires verification with either a TOTP code or backup code
    before MFA can be disabled. This also deletes all backup codes.

    Args:
        request: Disable request with verification code.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFAVerifyResponse with result of disabling MFA.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
        HTTPException: 423 if account is locked.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.disable_mfa(
            user_id=user_id,
            code=request.code,
            is_backup_code=request.is_backup_code,
        )
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except MFALockoutError as e:
        raise HTTPException(
            status_code=423,
            detail=f"Account locked due to too many failed attempts. Try again after {e.locked_until.isoformat()}",
        )


# =============================================================================
# Backup Code Endpoints
# =============================================================================


@router.post(
    "/backup-codes",
    response_model=BackupCodesResponse,
    summary="Generate backup codes",
    description="Generate new backup codes, invalidating any existing ones.",
)
async def generate_backup_codes(
    request: BackupCodesGenerateRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> BackupCodesResponse:
    """Generate new backup codes for the authenticated user.

    Creates a new set of one-time-use backup codes. Any existing codes
    are invalidated. The plaintext codes are only shown once.

    Requires TOTP verification before generating new codes.

    Args:
        request: Request with TOTP code and optional count.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        BackupCodesResponse with newly generated codes.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled or TOTP code is invalid.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.generate_backup_codes(
            user_id=user_id,
            code=request.code,
            count=request.count,
        )
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except InvalidCodeError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post(
    "/backup-codes/verify",
    response_model=MFAVerifyResponse,
    summary="Verify backup code",
    description="Verify a backup code for account recovery.",
)
async def verify_backup_code(
    request: BackupCodeVerifyRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFAVerifyResponse:
    """Verify a backup code for the authenticated user.

    Used when the user cannot access their authenticator app.
    Each backup code can only be used once.

    Args:
        request: Request with the backup code to verify.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFAVerifyResponse with verification result.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
        HTTPException: 423 if account is locked.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.verify_backup_code(user_id=user_id, code=request.code)
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except MFALockoutError as e:
        raise HTTPException(
            status_code=423,
            detail=f"Account locked due to too many failed attempts. Try again after {e.locked_until.isoformat()}",
        )


@router.get(
    "/backup-codes/status",
    response_model=BackupCodeStatusResponse,
    summary="Get backup code status",
    description="Get information about remaining backup codes.",
)
async def get_backup_code_status(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> BackupCodeStatusResponse:
    """Get backup code status for the authenticated user.

    Returns the count of total, used, and remaining backup codes.

    Args:
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        BackupCodeStatusResponse with backup code counts.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.get_backup_code_status(user_id=user_id)
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))


# =============================================================================
# IP Whitelist Endpoints
# =============================================================================


@router.get(
    "/ip-whitelist",
    response_model=IPWhitelistListResponse,
    summary="List whitelisted IPs",
    description="Get all IP addresses in the user's MFA whitelist.",
)
async def list_ip_whitelist(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> IPWhitelistListResponse:
    """List all IP whitelist entries for the authenticated user.

    Returns all IP addresses that can bypass MFA verification.

    Args:
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        IPWhitelistListResponse with list of whitelist entries.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        entries = await service.get_ip_whitelist(user_id=user_id)
        return IPWhitelistListResponse(
            items=entries,
            total=len(entries),
            skip=0,
            limit=len(entries),
        )
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post(
    "/ip-whitelist",
    response_model=IPWhitelistEntryResponse,
    summary="Add IP to whitelist",
    description="Add an IP address to the user's MFA whitelist.",
)
async def add_ip_to_whitelist(
    request: IPWhitelistCreateRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> IPWhitelistEntryResponse:
    """Add an IP address to the MFA whitelist.

    IPs in the whitelist can bypass MFA verification. Supports both
    individual IP addresses and CIDR ranges.

    Args:
        request: Request with IP address and optional description.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        IPWhitelistEntryResponse with the created entry.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        return await service.add_ip_to_whitelist(
            user_id=user_id,
            ip_address=request.ip_address,
            description=request.description,
        )
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.delete(
    "/ip-whitelist/{entry_id}",
    summary="Remove IP from whitelist",
    description="Remove an IP address from the user's MFA whitelist.",
)
async def remove_ip_from_whitelist(
    entry_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Remove an IP address from the MFA whitelist.

    Args:
        entry_id: Unique identifier of the whitelist entry to remove.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        Success message.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
        HTTPException: 404 if entry not found.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    try:
        success = await service.remove_ip_from_whitelist(
            user_id=user_id,
            entry_id=entry_id,
        )
        if not success:
            raise HTTPException(
                status_code=404,
                detail=f"IP whitelist entry with id {entry_id} not found",
            )
        return {"message": "IP removed from whitelist successfully"}
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post(
    "/ip-whitelist/check",
    response_model=IPWhitelistCheckResponse,
    summary="Check if IP is whitelisted",
    description="Check if a specific IP address is in the user's whitelist.",
)
async def check_ip_whitelisted(
    request: IPWhitelistCheckRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> IPWhitelistCheckResponse:
    """Check if an IP address is whitelisted.

    Can be used to determine if MFA verification is required for a
    particular IP address.

    Args:
        request: Request with the IP address to check.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        IPWhitelistCheckResponse with the check result.

    Raises:
        HTTPException: 401 if not authenticated.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)
    return await service.check_ip_whitelisted(
        user_id=user_id,
        ip_address=request.ip_address,
    )


# =============================================================================
# Session Validation Endpoint
# =============================================================================


@router.post(
    "/validate",
    response_model=MFAVerifyResponse,
    summary="Validate MFA for session",
    description="Validate that MFA has been completed for the current session.",
)
async def validate_mfa_session(
    request: MFAVerifyRequest,
    http_request: Request,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MFAVerifyResponse:
    """Validate MFA for the current session.

    Called during login flow to verify MFA before granting full access.
    Checks TOTP code and optionally IP whitelist.

    Args:
        request: Verification request with TOTP code.
        http_request: HTTP request for client IP extraction.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        MFAVerifyResponse with validation result.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 400 if MFA is not enabled.
        HTTPException: 423 if account is locked.
    """
    user_id = _get_user_id(current_user)
    service = MFAService(db)

    # Check if user's IP is whitelisted (bypass MFA)
    client_ip = _get_client_ip(http_request)
    if client_ip:
        ip_check = await service.check_ip_whitelisted(user_id, client_ip)
        if ip_check.is_whitelisted:
            return MFAVerifyResponse(
                verified=True,
                message="MFA bypassed - IP is whitelisted.",
                remaining_attempts=None,
                locked_until=None,
            )

    # Proceed with TOTP verification
    try:
        return await service.verify_totp(
            user_id=user_id,
            code=request.code,
            is_setup_verification=False,
        )
    except MFANotEnabledError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except MFALockoutError as e:
        raise HTTPException(
            status_code=423,
            detail=f"Account locked due to too many failed attempts. Try again after {e.locked_until.isoformat()}",
        )


# =============================================================================
# Admin Endpoints
# =============================================================================


@router.post(
    "/admin/reset-lockout/{target_user_id}",
    summary="Reset MFA lockout (admin)",
    description="Admin endpoint to reset MFA lockout for a user.",
)
async def admin_reset_lockout(
    target_user_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Reset MFA lockout for a user (admin function).

    Allows administrators to unlock accounts that have been locked
    due to too many failed MFA attempts.

    Args:
        target_user_id: User ID to reset lockout for.
        db: Async database session (injected).
        current_user: Authenticated admin user (injected).

    Returns:
        Success message.

    Raises:
        HTTPException: 401 if not authenticated.
        HTTPException: 403 if user is not an admin.
        HTTPException: 404 if target user MFA settings not found.
    """
    # Verify current user is an admin
    if not _is_admin(current_user):
        raise HTTPException(
            status_code=403,
            detail="Admin privileges required for this operation",
        )

    service = MFAService(db)
    success = await service.reset_lockout(target_user_id)

    if not success:
        raise HTTPException(
            status_code=404,
            detail=f"MFA settings not found for user {target_user_id}",
        )

    return {"message": f"MFA lockout reset successfully for user {target_user_id}"}


# =============================================================================
# Helper Functions
# =============================================================================


def _get_user_id(current_user: dict[str, Any]) -> UUID:
    """Extract user ID from the authenticated user payload.

    Args:
        current_user: Decoded JWT payload.

    Returns:
        UUID of the authenticated user.

    Raises:
        HTTPException: 401 if user ID cannot be extracted.
    """
    user_id = current_user.get("sub") or current_user.get("user_id")
    if user_id is None:
        raise HTTPException(
            status_code=401,
            detail="User ID not found in authentication token",
        )
    try:
        return UUID(str(user_id))
    except ValueError:
        raise HTTPException(
            status_code=401,
            detail="Invalid user ID format in authentication token",
        )


def _get_user_email(current_user: dict[str, Any]) -> str:
    """Extract user email from the authenticated user payload.

    Args:
        current_user: Decoded JWT payload.

    Returns:
        Email of the authenticated user.

    Raises:
        HTTPException: 401 if email cannot be extracted.
    """
    email = current_user.get("email")
    if email is None:
        # Fallback to using user ID if email not available
        user_id = current_user.get("sub") or current_user.get("user_id")
        return f"user_{user_id}@laya.app"
    return str(email)


def _get_client_ip(request: Request) -> Optional[str]:
    """Extract client IP address from the request.

    Handles both direct connections and requests through proxies.

    Args:
        request: The HTTP request.

    Returns:
        Client IP address or None if not determinable.
    """
    # Check X-Forwarded-For header for proxy scenarios
    forwarded_for = request.headers.get("X-Forwarded-For")
    if forwarded_for:
        # Take the first IP in the chain (original client)
        return forwarded_for.split(",")[0].strip()

    # Check X-Real-IP header
    real_ip = request.headers.get("X-Real-IP")
    if real_ip:
        return real_ip.strip()

    # Fall back to direct client host
    if request.client:
        return request.client.host

    return None


def _is_admin(current_user: dict[str, Any]) -> bool:
    """Check if the current user has admin privileges.

    Args:
        current_user: Decoded JWT payload.

    Returns:
        True if user is an admin, False otherwise.
    """
    # Check for admin role in various possible locations
    role = current_user.get("role") or current_user.get("roles")
    if isinstance(role, str):
        return role.lower() in ("admin", "administrator", "director")
    if isinstance(role, list):
        return any(r.lower() in ("admin", "administrator", "director") for r in role)

    # Check for is_admin flag
    return current_user.get("is_admin", False)
