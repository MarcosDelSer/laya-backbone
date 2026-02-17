# JWT Shared Secret Configuration Guide

## Overview

The LAYA authentication bridge uses a **shared secret** to enable secure cross-service authentication between Gibbon (PHP) and the AI Service (Python). Both services must use the **exact same secret** to sign and verify JWT tokens.

## Why a Shared Secret?

- **Gibbon** signs JWT tokens when users exchange their PHP session for a JWT token
- **AI Service** verifies these JWT tokens to authenticate requests
- Both operations use the HS256 algorithm with the same secret key
- This enables seamless single sign-on across both systems

## Architecture

```
┌─────────────────┐         ┌──────────────────┐
│     Gibbon      │         │   AI Service     │
│   (PHP 8.3)     │         │  (Python 3.11)   │
├─────────────────┤         ├──────────────────┤
│ auth_token.php  │         │ middleware/      │
│                 │         │   auth.py        │
│ Signs JWT       │◄────────┤ Verifies JWT     │
│ with SECRET     │  SAME   │ with SECRET      │
│                 │ SECRET  │                  │
└─────────────────┘         └──────────────────┘
        │                            │
        └────────────────┬───────────┘
                         │
                  JWT_SECRET_KEY
              (must be identical)
```

## Setup Instructions

### Step 1: Generate a Secure Secret

Use the provided script to generate a cryptographically secure secret:

```bash
./scripts/generate-jwt-secret.sh
```

This will output a secure random string like:
```
vK8f3nP9mH2jD1sL7wQ4xR6yT5uV0zA3bE8cF9gH1iJ2kL3mN4oP5qR6sT7uV8wX9yZ0aB1cD2eF3gH4iJ5kL6mN7oP8qR9sT0uV1w
```

**Alternative methods:**
```bash
# Using Python
python3 -c "import secrets; print(secrets.token_urlsafe(64))"

# Using OpenSSL
openssl rand -base64 64
```

### Step 2: Configure AI Service

1. Create or edit `ai-service/.env`:
```bash
cp ai-service/.env.example ai-service/.env
```

2. Add your generated secret:
```env
JWT_SECRET_KEY=vK8f3nP9mH2jD1sL7wQ4xR6yT5uV0zA3bE8cF9gH1iJ2kL3mN4oP5qR6sT7uV8wX9yZ0aB1cD2eF3gH4iJ5kL6mN7oP8qR9sT0uV1w
JWT_ALGORITHM=HS256
```

3. Restart the AI service:
```bash
docker-compose restart ai-service
# OR
cd ai-service && python -m uvicorn app.main:app --reload
```

### Step 3: Configure Gibbon

1. Create or edit `gibbon/.env`:
```bash
cp gibbon/.env.example gibbon/.env
```

2. Add the **same secret** (must match AI Service):
```env
JWT_SECRET_KEY=vK8f3nP9mH2jD1sL7wQ4xR6yT5uV0zA3bE8cF9gH1iJ2kL3mN4oP5qR6sT7uV8wX9yZ0aB1cD2eF3gH4iJ5kL6mN7oP8qR9sT0uV1w
```

3. Configure Docker to load the environment variable:

**Option A: Using docker-compose.yml**
```yaml
services:
  php-fpm:
    env_file:
      - ./gibbon/.env
    environment:
      - JWT_SECRET_KEY=${JWT_SECRET_KEY}
```

**Option B: Direct environment variable**
```yaml
services:
  php-fpm:
    environment:
      - JWT_SECRET_KEY=vK8f3nP9mH2jD1sL7wQ4xR6yT5uV0zA3bE8cF9gH1iJ2kL3mN4oP5qR6sT7uV8wX9yZ0aB1cD2eF3gH4iJ5kL6mN7oP8qR9sT0uV1w
```

4. Restart Gibbon services:
```bash
docker-compose restart php-fpm nginx
```

### Step 4: Verify Configuration

Test that both services are using the same secret:

1. **Get a JWT token from Gibbon:**
```bash
# Login to Gibbon first via browser (http://localhost:8080)
# Then request a token:
curl -X POST http://localhost:8080/modules/System/auth_token.php \
  --cookie "gibbonSession=<your-session-cookie>" \
  -H "Content-Type: application/json"
```

2. **Verify the token with AI Service:**
```bash
# Use the token from step 1
TOKEN="<token-from-gibbon>"

curl -X GET http://localhost:8000/api/v1/profile \
  -H "Authorization: Bearer $TOKEN"
```

If configured correctly, the AI Service will successfully verify the token and return user information.

## Configuration Files Reference

### AI Service (`ai-service/app/config.py`)

```python
class Settings(BaseSettings):
    # JWT configuration
    jwt_secret_key: str = "your_jwt_secret_key_change_in_production"
    jwt_algorithm: str = "HS256"

    class Config:
        env_file = ".env"
```

Loads from:
- Environment variable: `JWT_SECRET_KEY`
- File: `ai-service/.env`
- Default: `"your_jwt_secret_key_change_in_production"`

### Gibbon (`gibbon/modules/System/auth_token.php`)

```php
function getJWTSecret(): string {
    // Try environment variable first
    $secret = getenv('JWT_SECRET_KEY');
    if ($secret !== false && !empty($secret)) {
        return $secret;
    }

    // Default (should never be used in production)
    return 'your_jwt_secret_key_change_in_production';
}
```

Loads from:
- Environment variable: `JWT_SECRET_KEY`
- Default: `'your_jwt_secret_key_change_in_production'`

## Security Best Practices

### ✓ DO

1. **Use a cryptographically secure random generator**
   - Use the provided script or Python's `secrets` module
   - Minimum 32 characters, recommended 64+ characters

2. **Store secrets in environment variables**
   - Never commit to version control
   - Use `.env` files (gitignored)
   - Use Docker secrets or Kubernetes secrets in production

3. **Rotate secrets regularly**
   - Every 90 days recommended
   - Update both services simultaneously
   - Coordinate rotation to minimize downtime

4. **Use the same secret in both services**
   - Gibbon and AI Service must have identical `JWT_SECRET_KEY`
   - Verify configuration after any changes

5. **Monitor for secret exposure**
   - Check logs for accidental secret logging
   - Review code for hardcoded secrets
   - Use secret scanning tools

### ✗ DON'T

1. **Never commit secrets to version control**
   - Add `.env` to `.gitignore`
   - Use `.env.example` with placeholder values
   - Review commits before pushing

2. **Never share secrets insecurely**
   - No email, chat, or tickets
   - Use secure secret sharing tools if needed
   - Rotate if exposed

3. **Never use the default secret in production**
   - `your_jwt_secret_key_change_in_production` is for development only
   - Always generate a unique secret per environment

4. **Never hardcode secrets in source files**
   - Always use environment variables
   - Use configuration management

5. **Never use weak or predictable secrets**
   - No dictionary words, dates, or simple patterns
   - Always use cryptographically random generation

## Troubleshooting

### Problem: "Invalid authentication token"

**Cause:** Secrets don't match between services

**Solution:**
1. Verify both services have the same `JWT_SECRET_KEY`
2. Check for extra whitespace or encoding issues
3. Restart both services after changing secrets

### Problem: "Token has expired"

**Cause:** Token lifetime exceeded (default 1 hour)

**Solution:**
1. Request a new token from Gibbon
2. Consider adjusting token expiration if needed

### Problem: Environment variable not loaded

**Cause:** Docker/PHP not reading `.env` file

**Solution:**
```bash
# For Docker Compose
docker-compose config  # Verify environment variables
docker-compose restart  # Restart with new config

# For PHP-FPM
# Check php-fpm pool configuration
# Verify getenv('JWT_SECRET_KEY') returns the correct value
```

### Problem: "Token missing required 'sub' claim"

**Cause:** Token payload structure incorrect

**Solution:**
1. Verify Gibbon is creating tokens with all required fields
2. Check that both services are using HS256 algorithm
3. Verify the token is not corrupted during transmission

## Token Flow Diagram

```
┌─────────┐                ┌─────────┐               ┌────────────┐
│ Browser │                │ Gibbon  │               │ AI Service │
└────┬────┘                └────┬────┘               └─────┬──────┘
     │                          │                          │
     │ 1. Login (username/pwd)  │                          │
     ├─────────────────────────►│                          │
     │                          │                          │
     │ 2. PHP Session Cookie    │                          │
     │◄─────────────────────────┤                          │
     │                          │                          │
     │ 3. POST /auth_token.php  │                          │
     ├─────────────────────────►│                          │
     │                          │                          │
     │                          │ 4. Validate PHP Session  │
     │                          │    Generate JWT          │
     │                          │    Sign with SECRET      │
     │                          │                          │
     │ 5. JWT Token (signed)    │                          │
     │◄─────────────────────────┤                          │
     │                          │                          │
     │ 6. API Request + Bearer JWT                         │
     ├─────────────────────────────────────────────────────►│
     │                          │                          │
     │                          │                          │ 7. Verify JWT
     │                          │                          │    with SECRET
     │                          │                          │
     │ 8. API Response (authenticated)                     │
     │◄─────────────────────────────────────────────────────┤
     │                          │                          │
```

## Secret Rotation Procedure

When rotating the JWT secret (recommended every 90 days):

1. **Schedule maintenance window** (minimal downtime expected)

2. **Generate new secret:**
   ```bash
   ./scripts/generate-jwt-secret.sh
   ```

3. **Update both services simultaneously:**
   ```bash
   # Update AI Service
   echo "JWT_SECRET_KEY=<new-secret>" >> ai-service/.env

   # Update Gibbon
   echo "JWT_SECRET_KEY=<new-secret>" >> gibbon/.env
   ```

4. **Restart both services:**
   ```bash
   docker-compose restart ai-service php-fpm
   ```

5. **Notify users:**
   - All existing JWT tokens will be invalidated
   - Users will need to re-authenticate
   - PHP sessions remain valid

6. **Verify:**
   - Test token exchange endpoint
   - Test AI Service authentication
   - Monitor logs for errors

## Additional Resources

- [JWT.io](https://jwt.io/) - JWT debugger and documentation
- [OWASP JWT Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)
- [Python PyJWT Documentation](https://pyjwt.readthedocs.io/)
- `ai-service/app/auth/README.md` - Authentication bridge documentation
- `ai-service/app/middleware/auth.py` - Token verification implementation
- `gibbon/modules/System/auth_token.php` - Token generation implementation
