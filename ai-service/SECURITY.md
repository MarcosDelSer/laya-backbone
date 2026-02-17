# Security Configuration Guide

This document describes the security-related configuration options for the LAYA AI Service.

## Quick Start

1. **Copy the example environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Update the critical security settings in `.env`:**
   - `JWT_SECRET_KEY` - Must be changed to a cryptographically secure random value
   - `POSTGRES_PASSWORD` - Set a strong database password
   - `CORS_ORIGINS` - Configure allowed origins for production

3. **Never commit the `.env` file** - It contains sensitive secrets

## Environment Variables

### Critical Security Settings (MUST CHANGE IN PRODUCTION)

- **`JWT_SECRET_KEY`**: Secret key for signing JWT tokens
  - **CRITICAL**: Must be changed from default in production
  - Generate with: `python -c "import secrets; print(secrets.token_urlsafe(32))"`
  - Used for both JWT authentication tokens and CSRF tokens

- **`POSTGRES_PASSWORD`**: Database password
  - Use a strong, unique password in production

- **`CORS_ORIGINS`**: Allowed CORS origins
  - **Development**: Leave empty for localhost defaults
  - **Production**: Specify exact origins (never use wildcards)
  - Example: `CORS_ORIGINS=https://app.layaedu.com,https://parent.layaedu.com`

### Application Settings

- **`ENVIRONMENT`**: Application environment (development, staging, production)
  - Default: `development`
  - Controls security features and logging behavior

- **`DEBUG`**: Enable debug mode
  - Default: `true`
  - **MUST** be `false` in production for security

- **`LOG_LEVEL`**: Logging verbosity (DEBUG, INFO, WARNING, ERROR, CRITICAL)
  - Default: `INFO`

### Rate Limiting

- **`RATE_LIMIT_STORAGE_URI`**: Storage backend for rate limiting
  - **Development**: `memory://` (in-memory storage)
  - **Production**: `redis://localhost:6379/0` (Redis for distributed systems)
  - Example with auth: `redis://:password@redis-host:6379/0`

- **`RATE_LIMIT_GENERAL`**: Rate limit for general endpoints (requests per minute)
  - Default: `100`

- **`RATE_LIMIT_AUTH`**: Rate limit for auth endpoints (requests per minute)
  - Default: `10`

### CSRF Protection

- **`CSRF_TOKEN_EXPIRE_MINUTES`**: CSRF token expiration time
  - Default: `60` minutes

### JWT Configuration

- **`JWT_ALGORITHM`**: Algorithm for JWT signing
  - Default: `HS256` (recommended for symmetric signing)

- **`JWT_ACCESS_TOKEN_EXPIRE_MINUTES`**: JWT token expiration
  - Default: `60` minutes

- **`JWT_ISSUER`**: JWT issuer claim (iss)
  - Default: `laya-ai-service`
  - Used to identify the token issuer and prevent token confusion attacks

- **`JWT_AUDIENCE`**: JWT audience claim (aud)
  - Default: `laya-platform`
  - Used to identify intended token recipients and prevent cross-service attacks

## Security Features

### 1. CORS Lockdown
- Production: Only allows explicitly whitelisted origins
- Development: Defaults to localhost for convenience
- Configured via `CORS_ORIGINS` environment variable

### 2. Rate Limiting
- General endpoints: 100 requests/minute (configurable)
- Auth endpoints: 10 requests/minute (configurable)
- Storage: Memory (dev) or Redis (production)
- Prevents abuse and ensures fair resource usage

### 3. Input Validation
- Pydantic-based strict validation
- Field-level constraints (length, type, ranges)
- Protection against SQL injection and XSS

### 4. XSS Protection Headers
- Content-Security-Policy (CSP)
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- Prevents cross-site scripting and clickjacking

### 5. CSRF Protection
- JWT-based CSRF tokens
- Required for state-changing requests (POST, PUT, DELETE, PATCH)
- Tokens expire after configurable duration
- Prevents Cross-Site Request Forgery attacks

### 6. SQL Injection Protection
- All queries use SQLAlchemy ORM or parameterized queries
- No string concatenation in SQL queries
- Type validation prevents type confusion attacks

### 7. HTTPS Enforcement
- Automatic HTTP to HTTPS redirect in production
- HTTP Strict Transport Security (HSTS) headers
- Tells browsers to always use HTTPS
- Configured via `ENFORCE_HTTPS` environment variable
- nginx reverse proxy configuration for SSL/TLS termination
- See `nginx/https.conf.example` for production setup

### 8. Password Complexity Validation
- Enforces strong password requirements:
  - Minimum 8 characters
  - At least one uppercase letter (A-Z)
  - At least one lowercase letter (a-z)
  - At least one number (0-9)
- Available via `validate_password_complexity()` function
- Provides detailed error messages for validation failures
- Password strength checking with `get_password_strength()`
- Prevents common weak passwords

### 9. JWT Security & Token Revocation

The LAYA AI Service implements comprehensive JWT security measures based on OWASP JWT Security Best Practices and RFC 8725 (JWT Best Current Practices).

#### JWT Security Features

**Signature Verification:**
- All tokens are cryptographically signed using HMAC-SHA256 (HS256)
- Signature verification prevents token tampering
- Invalid signatures are immediately rejected

**Standard Claims Enforcement:**
- **Required Claims**: All tokens must include `sub`, `exp`, `iat`, `iss`, and `aud`
- **Expiration (`exp`)**: Tokens expire after configured duration (default: 60 minutes)
- **Issued At (`iat`)**: Timestamp when token was created
- **Issuer (`iss`)**: Identifies the token issuer (`laya-ai-service`)
- **Audience (`aud`)**: Identifies intended recipients (`laya-platform`)
- **Subject (`sub`)**: User identifier (cannot be overridden)

**Token Confusion Attack Prevention:**
- Issuer (`iss`) and Audience (`aud`) validation prevents tokens from:
  - Being used across different services
  - Being accepted from staging/dev in production
  - Cross-service token replay attacks
- Additional claims cannot override standard claims for security

**Algorithm Validation:**
- Only HS256 algorithm allowed (configurable via `JWT_ALGORITHM`)
- Prevents algorithm substitution attacks
- "none" algorithm explicitly blocked by PyJWT

**Protection Against Common JWT Vulnerabilities:**
- ✅ Prevents additional claims from overriding standard claims
- ✅ Enforces expiration claim requirement
- ✅ Validates issuer and audience
- ✅ Blocks algorithm confusion attacks
- ✅ Implements secure error handling
- ✅ Supports token revocation via blacklist

#### Token Revocation (Blacklist) System

The service implements a high-performance Redis-based token blacklist for immediate token revocation. This is critical for security events like:
- User logout
- Password changes
- Account suspension
- Security breaches
- Compromised tokens

**How Token Revocation Works:**

1. **Blacklist Operation:**
   ```python
   # When a user logs out or token needs revocation
   blacklist_service = TokenBlacklistService()
   await blacklist_service.add_to_blacklist(
       token="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
       user_id="user123",
       expires_at=token_expiration_datetime
   )
   ```

2. **Validation Check:**
   - Every authenticated request checks Redis blacklist (< 5ms)
   - Blacklisted tokens are immediately rejected with 401 Unauthorized
   - No database query needed (Redis is in-memory)

3. **Automatic Cleanup:**
   - Tokens automatically expire from blacklist when JWT would naturally expire
   - No manual cleanup needed (Redis TTL handles this)
   - Memory-efficient: only stores active tokens

**Performance Characteristics:**
- Blacklist check: < 5ms (Redis GET operation)
- Blacklist add: < 5ms (Redis SETEX operation)
- Total auth overhead: < 10ms per request
- Scales horizontally with Redis cluster

**Storage Efficiency:**
- Uses Redis TTL matching JWT expiration
- Automatic memory cleanup (no manual intervention)
- Namespace isolation with `blacklist:` prefix
- Stores minimal metadata: user_id and timestamp

**Production Deployment:**
For production, configure Redis via `RATE_LIMIT_STORAGE_URI`:
```bash
# Use Redis for token blacklist
RATE_LIMIT_STORAGE_URI=redis://:password@redis-host:6379/0
```

**Token Revocation Best Practices:**
1. **Always revoke tokens on sensitive actions:**
   - User logout (revoke current session)
   - Password change (revoke all user tokens)
   - Permission changes (revoke affected user tokens)
   - Account suspension (revoke all user tokens)

2. **Token expiration strategy:**
   - Use short-lived access tokens (60 minutes default)
   - Implement refresh tokens for longer sessions (not yet implemented)
   - Balance security vs user experience

3. **Security events requiring revocation:**
   - Suspicious activity detected
   - Token leaked in logs or errors
   - User reports compromised account
   - Admin-initiated security action

4. **Monitoring and auditing:**
   - Log all token blacklist operations
   - Monitor blacklist size and growth
   - Alert on unusual revocation patterns
   - Track token usage after revocation attempts

#### JWT Security Checklist

Before deploying to production, verify:

- [ ] `JWT_SECRET_KEY` is cryptographically secure (32+ characters)
- [ ] `JWT_SECRET_KEY` is different for each environment
- [ ] `JWT_ISSUER` is set correctly for your service
- [ ] `JWT_AUDIENCE` matches your application identifier
- [ ] Token expiration time is appropriate (not too long)
- [ ] Redis is configured for token blacklist
- [ ] Tokens are revoked on logout
- [ ] Tokens are revoked on password change
- [ ] All authenticated endpoints verify tokens
- [ ] Token validation errors are logged
- [ ] HTTPS is enforced (tokens only over encrypted connections)

#### Common JWT Security Mistakes to Avoid

**❌ DO NOT:**
- Store sensitive data in JWT payload (it's base64, not encrypted)
- Use weak or default secret keys
- Allow tokens without expiration
- Skip signature verification
- Accept tokens from untrusted issuers
- Use the same secret across environments
- Forget to revoke tokens on sensitive actions
- Store tokens in localStorage (XSS risk - use httpOnly cookies instead)

**✅ DO:**
- Use strong, randomly generated secret keys
- Set appropriate expiration times
- Validate all JWT claims (exp, iss, aud, etc.)
- Implement token revocation for sensitive actions
- Use different secrets per environment
- Monitor token usage and anomalies
- Store tokens securely (httpOnly cookies or secure storage)
- Rotate secret keys periodically

#### JWT Security References

For more information on JWT security:
- [OWASP JWT Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)
- [RFC 7519 - JSON Web Token (JWT)](https://datatracker.ietf.org/doc/html/rfc7519)
- [RFC 8725 - JWT Best Current Practices](https://datatracker.ietf.org/doc/html/rfc8725)
- Security Audit: `docs/jwt_security_audit.md`

## HTTPS Configuration

### Environment Variable

- **`ENFORCE_HTTPS`**: Enable HTTPS redirect and HSTS headers
  - Default: `false` (for local development)
  - **Production**: Set to `true` to enforce encrypted traffic
  - When enabled:
    - All HTTP requests are redirected to HTTPS (301 Permanent Redirect)
    - Strict-Transport-Security header is added to HTTPS responses
    - Browsers remember to use HTTPS for 1 year (31,536,000 seconds)
    - Respects X-Forwarded-Proto header for reverse proxy deployments

### Production HTTPS Setup

For production deployment with HTTPS, follow these steps:

1. **Configure Environment:**
   ```bash
   ENFORCE_HTTPS=true
   ```

2. **Set up SSL/TLS certificates:**
   - **Recommended**: Use Let's Encrypt for free SSL certificates
   - Install certbot: `apt-get install certbot python3-certbot-nginx`
   - Obtain certificate: `certbot --nginx -d api.yourdomain.com`
   - Certificates auto-renew automatically

3. **Configure nginx reverse proxy:**
   - Copy `nginx/https.conf.example` to `/etc/nginx/sites-available/laya-ai-service`
   - Update `server_name` with your domain
   - Update SSL certificate paths
   - Enable site: `ln -s /etc/nginx/sites-available/laya-ai-service /etc/nginx/sites-enabled/`
   - Test: `nginx -t`
   - Reload: `systemctl reload nginx`

4. **Verify HTTPS configuration:**
   - Test redirect: `curl -I http://api.yourdomain.com` (should return 301)
   - Test HSTS: `curl -I https://api.yourdomain.com` (should include Strict-Transport-Security)
   - SSL Labs test: https://www.ssllabs.com/ssltest/ (target: A+ rating)

5. **Important**: Ensure your nginx configuration sets the `X-Forwarded-Proto` header:
   ```nginx
   proxy_set_header X-Forwarded-Proto $scheme;
   proxy_set_header X-Forwarded-Ssl on;
   ```

### How It Works

The HTTPS enforcement middleware works in two layers:

1. **HTTPS Redirect Middleware** (application layer):
   - Checks if request is using HTTP
   - Checks X-Forwarded-Proto header (for reverse proxy deployments)
   - Redirects HTTP to HTTPS with 301 status code
   - Only active when ENFORCE_HTTPS=true

2. **HSTS Middleware** (browser layer):
   - Adds Strict-Transport-Security header to HTTPS responses
   - Tells browsers to always use HTTPS for future requests
   - max-age=31536000 (1 year)
   - includeSubDomains directive for comprehensive protection

### Development vs Production

**Development (local):**
- `ENFORCE_HTTPS=false` (default)
- No HTTPS redirect
- No HSTS header
- Works with http://localhost:8000

**Production (deployed):**
- `ENFORCE_HTTPS=true`
- All HTTP traffic redirected to HTTPS
- HSTS header tells browsers to use HTTPS
- nginx handles SSL/TLS termination
- FastAPI receives X-Forwarded-Proto header

## Production Deployment Checklist

Before deploying to production, ensure:

### Critical Security Settings
- [ ] `JWT_SECRET_KEY` changed to cryptographically secure random value (32+ characters)
- [ ] `JWT_SECRET_KEY` is unique per environment (dev, staging, production)
- [ ] `JWT_ISSUER` configured correctly (default: `laya-ai-service`)
- [ ] `JWT_AUDIENCE` configured correctly (default: `laya-platform`)
- [ ] `JWT_ACCESS_TOKEN_EXPIRE_MINUTES` set appropriately (default: 60)
- [ ] `POSTGRES_PASSWORD` set to strong password
- [ ] `ENVIRONMENT` set to `production`
- [ ] `DEBUG` set to `false`

### Network & CORS Security
- [ ] `CORS_ORIGINS` configured with specific allowed origins (no wildcards)
- [ ] `ENFORCE_HTTPS` set to `true` to enforce encrypted traffic
- [ ] SSL/TLS certificates configured (use Let's Encrypt)
- [ ] nginx reverse proxy configured with HTTPS (see nginx/https.conf.example)
- [ ] X-Forwarded-Proto header configured in nginx
- [ ] HTTPS redirect tested (curl -I http://yourdomain.com)
- [ ] HSTS header verified (curl -I https://yourdomain.com)
- [ ] SSL Labs test passed with A+ rating

### Token Revocation & Performance
- [ ] `RATE_LIMIT_STORAGE_URI` configured to use Redis (required for token blacklist)
- [ ] Redis connection tested and verified
- [ ] Token blacklist functionality tested (login/logout flow)
- [ ] Token revocation works on password change
- [ ] Token validation performance < 10ms verified

### General Security
- [ ] `.env` file is in `.gitignore` and never committed
- [ ] All external API keys configured (OpenAI, etc.)
- [ ] Webhook secrets configured if using webhooks
- [ ] SMTP settings configured if sending emails
- [ ] Monitoring/logging services configured (Sentry, etc.)
- [ ] All security headers reviewed
- [ ] Firewall rules configured
- [ ] Database backups configured
- [ ] JWT security audit reviewed (see docs/jwt_security_audit.md)
- [ ] Authentication flows tested end-to-end
- [ ] Rate limiting verified for auth endpoints

## Security Best Practices

1. **Secrets Management**
   - Never hardcode secrets in source code
   - Use environment variables for all sensitive configuration
   - Rotate secrets regularly (JWT keys, API keys, passwords)
   - Use different secrets for each environment
   - Generate JWT secrets with: `python -c "import secrets; print(secrets.token_urlsafe(32))"`
   - Store secrets in secure vaults (HashiCorp Vault, AWS Secrets Manager, etc.)

2. **Access Control**
   - Use strong passwords (enforced via password complexity validation)
   - Password requirements: min 8 chars, uppercase, lowercase, number
   - Use `validate_password_complexity()` in user registration/password change flows
   - Implement least-privilege access
   - Review and audit access logs regularly

3. **JWT Token Management**
   - **Token Expiration**: Use short-lived tokens (60 minutes or less)
   - **Token Revocation**: Always revoke tokens on logout, password change, or security events
   - **Token Storage**: Use httpOnly cookies (prevents XSS attacks) or secure storage
   - **Token Transmission**: Only send tokens over HTTPS (never HTTP)
   - **Token Validation**: Always validate all JWT claims (exp, iss, aud, sub)
   - **Token Monitoring**: Track token usage patterns and anomalies
   - **Blacklist Performance**: Ensure Redis is used for < 5ms blacklist checks
   - **Secret Rotation**: Plan for JWT secret key rotation without service disruption

4. **Network Security**
   - Always use HTTPS in production
   - Configure firewall rules to limit access
   - Use VPN or private networks for database access
   - Enable SSL/TLS for Redis and database connections

5. **Monitoring & Incident Response**
   - Monitor rate limit violations
   - Track authentication failures
   - Track token revocation events
   - Monitor Redis blacklist size and performance
   - Set up alerts for security events:
     - Unusual token revocation patterns
     - Multiple failed authentication attempts
     - Tokens used after revocation attempts
     - Redis connection failures
   - Regular security audits (review docs/jwt_security_audit.md)
   - Maintain incident response playbook for compromised tokens

## Need Help?

For security concerns or questions:
- Review the [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- Consult the FastAPI [security documentation](https://fastapi.tiangolo.com/tutorial/security/)
- Contact the LAYA security team

## Security Vulnerability Reporting

If you discover a security vulnerability, please report it to:
- Email: security@layaedu.com
- Do NOT open a public GitHub issue for security vulnerabilities
