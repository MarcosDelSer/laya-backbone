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

- [ ] `JWT_SECRET_KEY` changed to cryptographically secure random value
- [ ] `POSTGRES_PASSWORD` set to strong password
- [ ] `ENVIRONMENT` set to `production`
- [ ] `DEBUG` set to `false`
- [ ] `CORS_ORIGINS` configured with specific allowed origins (no wildcards)
- [ ] `RATE_LIMIT_STORAGE_URI` configured to use Redis
- [ ] `ENFORCE_HTTPS` set to `true` to enforce encrypted traffic
- [ ] SSL/TLS certificates configured (use Let's Encrypt)
- [ ] nginx reverse proxy configured with HTTPS (see nginx/https.conf.example)
- [ ] X-Forwarded-Proto header configured in nginx
- [ ] HTTPS redirect tested (curl -I http://yourdomain.com)
- [ ] HSTS header verified (curl -I https://yourdomain.com)
- [ ] SSL Labs test passed with A+ rating
- [ ] `.env` file is in `.gitignore` and never committed
- [ ] All external API keys configured (OpenAI, etc.)
- [ ] Webhook secrets configured if using webhooks
- [ ] SMTP settings configured if sending emails
- [ ] Monitoring/logging services configured (Sentry, etc.)
- [ ] All security headers reviewed
- [ ] Firewall rules configured
- [ ] Database backups configured

## Security Best Practices

1. **Secrets Management**
   - Never hardcode secrets in source code
   - Use environment variables for all sensitive configuration
   - Rotate secrets regularly (JWT keys, API keys, passwords)
   - Use different secrets for each environment

2. **Access Control**
   - Use strong passwords (min 8 chars, mixed case, numbers)
   - Implement least-privilege access
   - Review and audit access logs regularly

3. **Network Security**
   - Always use HTTPS in production
   - Configure firewall rules to limit access
   - Use VPN or private networks for database access
   - Enable SSL/TLS for Redis and database connections

4. **Monitoring**
   - Monitor rate limit violations
   - Track authentication failures
   - Set up alerts for security events
   - Regular security audits

## Need Help?

For security concerns or questions:
- Review the [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- Consult the FastAPI [security documentation](https://fastapi.tiangolo.com/tutorial/security/)
- Contact the LAYA security team

## Security Vulnerability Reporting

If you discover a security vulnerability, please report it to:
- Email: security@layaedu.com
- Do NOT open a public GitHub issue for security vulnerabilities
