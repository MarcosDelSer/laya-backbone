# Manual Verification Guide - Rate Limiting Tests

## Quick Verification (As per Spec)

### Test 1: Basic API Endpoint

```bash
curl -X GET http://localhost:8000/ -H "Content-Type: application/json"
```

**Expected Result:**
- **Status**: 200
- **Response**: JSON with service health status
- **Headers**: Should include X-Frame-Options, X-Content-Type-Options

**Sample Response:**
```json
{
  "status": "healthy",
  "service": "ai-service",
  "version": "0.1.0",
  "request_id": "...",
  "correlation_id": "..."
}
```

### Test 2: Verify Security Headers

```bash
curl -I http://localhost:8000/
```

**Expected Headers:**
```
HTTP/1.1 200 OK
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Content-Security-Policy: ...
```

### Test 3: Test Rate Limiting (Manual)

Make multiple rapid requests to test rate limiting:

```bash
# Make 12 requests in quick succession
for i in {1..12}; do
  echo "Request $i:"
  curl -s -o /dev/null -w "Status: %{http_code}\n" http://localhost:8000/
  sleep 0.1
done
```

**Expected Result:**
- First 100 requests: Status 200
- After 100 requests in 60 seconds: Status 429

**For Auth Endpoints (lower limit):**

```bash
# Generate a token first (in Python)
cd ai-service
python -c "from app.auth.jwt import create_token; print(create_token('test_user', 300))"

# Use the token in requests
TOKEN="<generated_token>"

# Make 12 requests
for i in {1..12}; do
  echo "Request $i:"
  curl -s -o /dev/null -w "Status: %{http_code}\n" \
    http://localhost:8000/protected \
    -H "Authorization: Bearer $TOKEN"
  sleep 0.1
done
```

**Expected Result:**
- First 10 requests: Status 200
- Requests 11-12: Status 429 (rate limited)

### Test 4: Verify Rate Limit Response

```bash
# After hitting rate limit, check response details
curl -v http://localhost:8000/ \
  -H "Content-Type: application/json"
```

**Expected 429 Response:**
```json
{
  "detail": "Rate limit exceeded: 100 per 1 minute"
}
```

**Expected Headers:**
```
HTTP/1.1 429 Too Many Requests
Retry-After: 45
```

## Automated Tests

For comprehensive automated testing, use the provided test scripts:

### Quick Bash Test
```bash
cd ai-service
./verify_rate_limiting.sh
```

### Comprehensive Python Test
```bash
cd ai-service
python test_rate_limiting_api.py
```

## Prerequisites

Before running tests, ensure:

1. **Redis is running:**
   ```bash
   redis-cli ping
   # Expected: PONG
   ```

2. **AI Service is running:**
   ```bash
   cd ai-service
   uvicorn app.main:app --reload --port 8000
   ```

3. **Environment configured:**
   ```bash
   # Check .env file
   grep RATE_LIMIT ai-service/.env

   # Should show:
   # RATE_LIMIT_GENERAL=100
   # RATE_LIMIT_AUTH=10
   # RATE_LIMIT_STORAGE_URI=redis://localhost:6379/0
   ```

## Verification Checklist

- [ ] Basic endpoint returns 200 status
- [ ] Security headers present (X-Frame-Options, X-Content-Type-Options)
- [ ] Rate limiting enforces limits (429 after threshold)
- [ ] Retry-After header present in 429 responses
- [ ] Different rate limits for general vs auth endpoints
- [ ] CORS headers configured correctly

## Troubleshooting

### Service Won't Start

**Check Redis:**
```bash
redis-cli ping
```

**Check logs:**
```bash
cd ai-service
uvicorn app.main:app --reload --log-level debug
```

### Rate Limiting Not Working

**Check Redis keys:**
```bash
redis-cli KEYS "fastapi-limiter:*"
```

**Check configuration:**
```bash
grep RATE_LIMIT ai-service/.env
```

### Headers Missing

**Verify middleware registration:**
```bash
grep -A5 "get_xss_protection_middleware\|get_hsts_middleware" ai-service/app/main.py
```

## Success Criteria

✅ All manual tests pass
✅ Automated tests pass
✅ Security headers present
✅ Rate limiting functional
✅ Documentation complete
