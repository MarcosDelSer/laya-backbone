# LAYA Scripts

Utility scripts for LAYA system configuration and maintenance.

## JWT Authentication Scripts

### generate-jwt-secret.sh

Generates a cryptographically secure random string for use as a JWT signing secret.

**Usage:**
```bash
./scripts/generate-jwt-secret.sh
```

**Output:**
- Displays a secure random secret suitable for JWT signing
- Provides instructions for adding to configuration files
- Includes security reminders

**Requirements:**
- Python 3, OpenSSL, or /dev/urandom (one of these)

**Example:**
```bash
$ ./scripts/generate-jwt-secret.sh

============================================================================
 LAYA JWT Secret Generator
============================================================================

Generating cryptographically secure JWT secret...

✓ Generated using: Python secrets module

============================================================================
 Your JWT Secret (keep this secure!):
============================================================================

vK8f3nP9mH2jD1sL7wQ4xR6yT5uV0zA3bE8cF9gH1iJ2kL3mN4oP5qR6sT7uV8wX9yZ0...

============================================================================
 Next Steps:
============================================================================
...
```

### verify-jwt-config.py

Verifies that both Gibbon and AI Service are configured with the same JWT secret.

**Usage:**
```bash
python3 scripts/verify-jwt-config.py
```

**Features:**
- Checks AI Service configuration (`ai-service/.env`, environment)
- Checks Gibbon configuration (`gibbon/.env`, environment)
- Compares secrets to ensure they match
- Validates secret strength (length, randomness)
- Provides recommendations for fixing issues
- Color-coded output for easy reading

**Example Output:**
```bash
$ python3 scripts/verify-jwt-config.py

============================================================================
 JWT Configuration Verification
============================================================================

Checking AI Service configuration...
✓ Found JWT secret
  Source: ai-service/.env
  Length: 86 characters
✓ Secret appears strong

Checking Gibbon configuration...
✓ Found JWT secret
  Source: gibbon/.env
  Length: 86 characters
✓ Secret appears strong

Comparing secrets...
✓ Secrets match! Cross-service authentication will work.

============================================================================
 Recommendations
============================================================================

For detailed setup instructions, see:
   docs/JWT_SHARED_SECRET_SETUP.md

============================================================================
 Summary
============================================================================

✓ Configuration is correct! ✓
  Both services are using the same strong JWT secret.
```

**Exit Codes:**
- `0` - Configuration is correct
- `1` - Configuration needs attention

## Workflow

### Initial Setup

1. Generate a secure JWT secret:
   ```bash
   ./scripts/generate-jwt-secret.sh
   ```

2. Copy the generated secret to both configuration files:
   ```bash
   # Add to ai-service/.env
   echo "JWT_SECRET_KEY=<generated-secret>" >> ai-service/.env

   # Add to gibbon/.env
   echo "JWT_SECRET_KEY=<generated-secret>" >> gibbon/.env
   ```

3. Verify configuration:
   ```bash
   python3 scripts/verify-jwt-config.py
   ```

4. Restart services:
   ```bash
   docker-compose restart ai-service php-fpm
   ```

### Secret Rotation (Every 90 Days)

1. Generate a new secret:
   ```bash
   ./scripts/generate-jwt-secret.sh
   ```

2. Update both services simultaneously:
   ```bash
   # Update AI Service
   sed -i 's/JWT_SECRET_KEY=.*/JWT_SECRET_KEY=<new-secret>/' ai-service/.env

   # Update Gibbon
   sed -i 's/JWT_SECRET_KEY=.*/JWT_SECRET_KEY=<new-secret>/' gibbon/.env
   ```

3. Verify before restart:
   ```bash
   python3 scripts/verify-jwt-config.py
   ```

4. Restart both services:
   ```bash
   docker-compose restart ai-service php-fpm
   ```

5. Notify users (existing tokens will be invalidated)

## Related Documentation

- [JWT Shared Secret Setup Guide](../docs/JWT_SHARED_SECRET_SETUP.md) - Comprehensive setup guide
- [Authentication Bridge README](../ai-service/app/auth/README.md) - Authentication implementation docs
- [AI Service Config](../ai-service/app/config.py) - AI Service configuration
- [Gibbon Token Endpoint](../gibbon/modules/System/auth_token.php) - Gibbon token generation

## Security Notes

- **Never commit secrets to version control** - Always use `.env` files
- **Never share secrets insecurely** - Use secure channels only
- **Rotate secrets regularly** - Every 90 days recommended
- **Use strong secrets** - 64+ characters, cryptographically random
- **Keep secrets synchronized** - Both services must use the same secret
