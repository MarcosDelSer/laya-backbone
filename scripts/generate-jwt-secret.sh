#!/bin/bash
# Generate a secure JWT secret for LAYA authentication bridge
#
# This script generates a cryptographically secure random string suitable
# for use as a JWT signing secret. The same secret must be used in both
# the Gibbon and AI Service configurations.
#
# Usage:
#   ./scripts/generate-jwt-secret.sh
#
# The generated secret will be printed to stdout and can be added to
# your .env files for both services.

set -e

echo "============================================================================"
echo " LAYA JWT Secret Generator"
echo "============================================================================"
echo ""
echo "Generating cryptographically secure JWT secret..."
echo ""

# Check if Python 3 is available
if command -v python3 &> /dev/null; then
    SECRET=$(python3 -c "import secrets; print(secrets.token_urlsafe(64))")
    METHOD="Python secrets module"
# Check if openssl is available
elif command -v openssl &> /dev/null; then
    SECRET=$(openssl rand -base64 64 | tr -d '\n')
    METHOD="OpenSSL"
# Fallback to /dev/urandom (less portable)
elif [ -f /dev/urandom ]; then
    SECRET=$(head -c 64 /dev/urandom | base64 | tr -d '\n')
    METHOD="/dev/urandom"
else
    echo "ERROR: No suitable random generator found!"
    echo "Please install Python 3 or OpenSSL to generate a secure secret."
    exit 1
fi

echo "✓ Generated using: $METHOD"
echo ""
echo "============================================================================"
echo " Your JWT Secret (keep this secure!):"
echo "============================================================================"
echo ""
echo "$SECRET"
echo ""
echo "============================================================================"
echo " Next Steps:"
echo "============================================================================"
echo ""
echo "1. Copy the secret above"
echo ""
echo "2. Add to AI Service configuration:"
echo "   File: ai-service/.env"
echo "   Line: JWT_SECRET_KEY=$SECRET"
echo ""
echo "3. Add to Gibbon configuration:"
echo "   File: gibbon/.env"
echo "   Line: JWT_SECRET_KEY=$SECRET"
echo ""
echo "4. Restart both services to apply the new secret"
echo ""
echo "============================================================================"
echo " Security Reminders:"
echo "============================================================================"
echo ""
echo "✓ NEVER commit this secret to version control"
echo "✓ NEVER share this secret in chat/email"
echo "✓ NEVER hardcode this secret in source files"
echo "✓ ALWAYS use environment variables or secrets management"
echo "✓ ROTATE this secret every 90 days"
echo "✓ USE the SAME secret in both Gibbon and AI Service"
echo ""
echo "============================================================================"
