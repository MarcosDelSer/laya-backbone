# Gzip Compression Middleware

## Overview

The Gzip Compression Middleware automatically compresses HTTP responses to reduce bandwidth usage and improve API response times. It intelligently compresses text-based content while avoiding compression of already-compressed formats like images and videos.

## Features

- **Automatic Compression**: Compresses responses when client supports gzip (Accept-Encoding: gzip)
- **Intelligent Content Type Detection**: Only compresses text-based content types
- **Configurable Thresholds**: Set minimum size and compression level
- **Performance Optimized**: Skips compression for small responses and already-compressed content
- **Standards Compliant**: Adds proper Content-Encoding and Vary headers

## Configuration

### Basic Setup

The middleware is already configured in `app/main.py`:

```python
from app.middleware import GzipCompressionMiddleware

app.add_middleware(GzipCompressionMiddleware, minimum_size=500, compresslevel=6)
```

### Configuration Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `minimum_size` | int | 500 | Minimum response size in bytes to compress |
| `compresslevel` | int | 6 | Gzip compression level (1-9) |
| `compressible_types` | Set[str] | See below | Content types to compress |
| `excluded_types` | Set[str] | See below | Content types to never compress |

### Compression Levels

- **Level 1**: Fastest compression, least space savings (~50-60% reduction)
- **Level 6** (default): Balanced compression speed and ratio (~65-75% reduction)
- **Level 9**: Maximum compression, slower (~70-80% reduction)

**Recommendation**: Use level 6 for most applications. Only use level 9 for very high-traffic APIs where bandwidth savings outweigh CPU costs.

## Compressible Content Types

The middleware compresses these content types by default:

### Text Formats
- `text/html`
- `text/css`
- `text/plain`
- `text/xml`
- `text/csv`
- `text/javascript`

### Application Formats
- `application/json`
- `application/javascript`
- `application/xml`
- `application/xhtml+xml`
- `application/rss+xml`
- `application/atom+xml`
- `application/ld+json`
- `application/geo+json`
- `application/manifest+json`
- `application/vnd.api+json`

### Generic Text
- Any content type starting with `text/*`

## Excluded Content Types

These content types are never compressed (already compressed):

### Images
- `image/png`, `image/jpg`, `image/jpeg`
- `image/gif`, `image/webp`, `image/avif`

### Videos
- `video/mp4`, `video/mpeg`, `video/webm`

### Audio
- `audio/mpeg`, `audio/ogg`, `audio/wav`

### Archives
- `application/zip`, `application/gzip`
- `application/x-bzip2`, `application/x-7z-compressed`

### Other
- `application/pdf`
- `application/octet-stream`
- `text/event-stream` (Server-sent events)

## Usage Examples

### Default Configuration

```python
from fastapi import FastAPI
from app.middleware import GzipCompressionMiddleware

app = FastAPI()
app.add_middleware(GzipCompressionMiddleware)
```

### Custom Configuration

```python
from app.middleware.compression import create_compression_middleware

# High compression for bandwidth-constrained environments
HighCompressionMiddleware = create_compression_middleware(
    compresslevel=9,
    minimum_size=1000
)

app.add_middleware(HighCompressionMiddleware)
```

### Adding Custom Compressible Types

```python
from app.middleware.compression import create_compression_middleware

# Add custom content types
CustomMiddleware = create_compression_middleware(
    compressible_types={"application/vnd.myapp+json"},
    excluded_types={"text/custom-no-compress"}
)

app.add_middleware(CustomMiddleware)
```

## How It Works

1. **Request Check**: Middleware checks if client sent `Accept-Encoding: gzip` header
2. **Content Type Check**: Verifies the response content type should be compressed
3. **Size Check**: Ensures response is larger than minimum threshold (500 bytes default)
4. **Compression**: Applies gzip compression at configured level
5. **Headers**: Sets `Content-Encoding: gzip` and `Vary: Accept-Encoding`
6. **Response**: Sends compressed response with updated Content-Length

## Performance Impact

### Bandwidth Savings

Typical compression ratios by content type:

| Content Type | Original Size | Compressed Size | Savings |
|--------------|---------------|-----------------|---------|
| JSON (API responses) | 10 KB | 2 KB | 80% |
| HTML (pages) | 50 KB | 12 KB | 76% |
| CSS (stylesheets) | 100 KB | 15 KB | 85% |
| JavaScript | 200 KB | 55 KB | 72% |
| XML (data) | 20 KB | 4 KB | 80% |

### CPU Impact

Compression adds minimal CPU overhead:

- **Level 1**: ~0.1ms per request (10KB response)
- **Level 6**: ~0.3ms per request (10KB response)
- **Level 9**: ~0.8ms per request (10KB response)

For most APIs, the bandwidth savings far outweigh the CPU cost.

### Network Savings

For a typical API returning 10KB JSON responses:

- **100 requests/second**: Saves ~800KB/s bandwidth (~2.8GB/hour)
- **1,000 requests/second**: Saves ~8MB/s bandwidth (~28GB/hour)

## Best Practices

### 1. Order Matters

Add compression middleware **last** (closest to the application):

```python
# Correct order
app.add_middleware(CORSMiddleware)
app.add_middleware(CacheHeadersMiddleware)
app.add_middleware(GzipCompressionMiddleware)  # Last!
```

This ensures all headers added by other middleware are also compressed.

### 2. Set Appropriate Minimum Size

Don't compress tiny responses:

```python
# Good: Skip compression for small responses
app.add_middleware(GzipCompressionMiddleware, minimum_size=500)

# Bad: Compress everything (wastes CPU)
app.add_middleware(GzipCompressionMiddleware, minimum_size=0)
```

### 3. Use Appropriate Compression Level

Balance compression ratio vs. CPU usage:

```python
# Good for most APIs (balanced)
app.add_middleware(GzipCompressionMiddleware, compresslevel=6)

# Good for high-traffic (faster)
app.add_middleware(GzipCompressionMiddleware, compresslevel=4)

# Good for bandwidth-constrained (smaller)
app.add_middleware(GzipCompressionMiddleware, compresslevel=9)
```

### 4. Don't Compress Pre-Compressed Content

The middleware automatically skips:
- Images (PNG, JPEG, WebP, etc.)
- Videos (MP4, WebM, etc.)
- Archives (ZIP, GZIP, etc.)
- PDFs

### 5. Test with Real Clients

Always test compression with real HTTP clients:

```bash
# Test with curl
curl -H "Accept-Encoding: gzip" http://localhost:8000/api/data -v

# Verify compression
curl -H "Accept-Encoding: gzip" http://localhost:8000/api/data \
  --compressed -w "\nSize: %{size_download} bytes\n"
```

## Monitoring

### Check Compression Effectiveness

```python
import logging

logger = logging.getLogger(__name__)

@app.middleware("http")
async def log_compression(request: Request, call_next):
    response = await call_next(request)

    if "content-encoding" in response.headers:
        logger.info(
            f"Compressed {request.url.path}: "
            f"{response.headers.get('content-length')} bytes"
        )

    return response
```

### Metrics to Track

1. **Compression Ratio**: Compare compressed vs. uncompressed sizes
2. **CPU Usage**: Monitor server CPU during high load
3. **Response Time**: Measure compression overhead
4. **Bandwidth Usage**: Track network traffic savings

## Troubleshooting

### Issue: Responses Not Being Compressed

**Possible causes:**

1. **Client doesn't support gzip**: Check for `Accept-Encoding: gzip` header
2. **Response too small**: Increase `minimum_size` threshold
3. **Content type excluded**: Check if content type is in excluded list
4. **Already compressed**: Response has existing `Content-Encoding` header

**Solution:**
```python
# Add debug logging
import logging
logging.basicConfig(level=logging.DEBUG)
```

### Issue: High CPU Usage

**Possible causes:**

1. **Compression level too high**: Using level 9 on high-traffic API
2. **Minimum size too low**: Compressing tiny responses

**Solution:**
```python
# Reduce compression level and increase minimum size
app.add_middleware(
    GzipCompressionMiddleware,
    compresslevel=4,  # Faster
    minimum_size=1000  # Skip small responses
)
```

### Issue: Content-Length Mismatch

**Possible causes:**

1. **TestClient auto-decompression**: Test clients decompress automatically
2. **Proxy interference**: Reverse proxy is modifying responses

**Solution:**

Test with real HTTP client (curl) instead of TestClient.

## Testing

### Unit Tests

Run the compression middleware tests:

```bash
pytest tests/middleware/test_compression.py -v
```

### Integration Tests

Test with real HTTP client:

```bash
# Test compression is applied
curl -H "Accept-Encoding: gzip" http://localhost:8000/api/data -v \
  | grep "content-encoding: gzip"

# Test compression is skipped without header
curl http://localhost:8000/api/data -v \
  | grep -v "content-encoding"
```

### Performance Tests

Benchmark compression overhead:

```bash
# Install Apache Bench
brew install httpd  # macOS

# Test without compression
ab -n 1000 -c 10 http://localhost:8000/api/data

# Test with compression
ab -n 1000 -c 10 -H "Accept-Encoding: gzip" http://localhost:8000/api/data
```

## Advanced Topics

### Custom Compression Logic

Extend the middleware for custom behavior:

```python
from app.middleware.compression import GzipCompressionMiddleware

class CustomCompressionMiddleware(GzipCompressionMiddleware):
    def __init__(self, app, **kwargs):
        super().__init__(app, **kwargs)
        # Add custom logic

    async def __call__(self, scope, receive, send):
        # Custom pre-processing
        await super().__call__(scope, receive, send)
        # Custom post-processing
```

### Compression with Caching

Combine with cache headers middleware:

```python
# Cached compressed responses are served instantly
app.add_middleware(CacheHeadersMiddleware)
app.add_middleware(GzipCompressionMiddleware)
```

The combination provides:
- **First request**: Compress and cache
- **Subsequent requests**: Serve compressed from cache (no compression overhead)

### Content Negotiation

Support multiple encodings:

```python
# Future enhancement: Support brotli, deflate, etc.
# Current implementation only supports gzip
```

## Security Considerations

### 1. BREACH Attack Prevention

Gzip compression can expose encrypted data to BREACH attacks. Mitigate by:

1. **Use CSRF tokens**: Add random data to responses
2. **Disable for sensitive endpoints**: Skip compression for auth endpoints
3. **Rate limiting**: Prevent repeated requests

```python
from app.middleware.compression import create_compression_middleware

# Don't compress auth endpoints
SafeCompressionMiddleware = create_compression_middleware(
    excluded_types={"application/json"}  # Skip if needed
)
```

### 2. Compression Bombs

The middleware protects against compression bombs by:

1. **Size limit**: Only compresses responses up to reasonable size
2. **Streaming**: Doesn't load entire response into memory
3. **Timeout**: Compression completes quickly or fails

## Migration Guide

### From Starlette GZipMiddleware

If you're currently using Starlette's GZipMiddleware:

```python
# Old code
from starlette.middleware.gzip import GZipMiddleware
app.add_middleware(GZipMiddleware, minimum_size=1000)

# New code
from app.middleware import GzipCompressionMiddleware
app.add_middleware(GzipCompressionMiddleware, minimum_size=1000)
```

Our middleware provides:
- Better content type handling
- Configurable excluded types
- Factory function for custom configuration
- Better test coverage

## References

- [RFC 7230 - HTTP/1.1 Message Syntax](https://tools.ietf.org/html/rfc7230)
- [RFC 1952 - GZIP File Format](https://tools.ietf.org/html/rfc1952)
- [MDN - Content-Encoding](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Encoding)
- [FastAPI Middleware](https://fastapi.tiangolo.com/tutorial/middleware/)

## Support

For issues or questions:

1. Check the troubleshooting section above
2. Review test cases in `tests/middleware/test_compression.py`
3. See implementation in `app/middleware/compression.py`
