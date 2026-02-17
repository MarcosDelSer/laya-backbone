# LAYA Deployment Runbook: Vercel + Hetzner

This runbook provides step-by-step instructions for deploying LAYA to production using **Vercel** for the frontend and **Hetzner** for the backend services.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Architecture Overview](#architecture-overview)
3. [Parallel Setup Tasks](#parallel-setup-tasks)
4. [Hetzner Server Setup](#hetzner-server-setup)
5. [Backend Deployment](#backend-deployment)
6. [Vercel Frontend Deployment](#vercel-frontend-deployment)
7. [SSL/TLS Configuration](#ssltls-configuration)
8. [Verification](#verification)
9. [Rollback Procedures](#rollback-procedures)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Accounts Required

- [ ] **Vercel Account**: [Sign up at vercel.com](https://vercel.com/signup)
- [ ] **Hetzner Cloud Account**: [Sign up at hetzner.com](https://accounts.hetzner.com/signUp)
- [ ] **GitHub/GitLab Account**: For repository access

### CLI Tools Required

Install these on your local machine:

```bash
# Vercel CLI
npm install -g vercel

# Hetzner CLI (optional, for server management)
brew install hcloud  # macOS
# or download from https://github.com/hetznercloud/cli

# Verify installations
vercel --version
hcloud version  # optional
```

### Credentials Required

- [ ] SSH key pair for Hetzner server access
- [ ] JWT secret key (shared between Gibbon and AI Service)
- [ ] Database credentials
- [ ] (Optional) LLM API keys (OpenAI, Anthropic, etc.)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                          Internet                                │
└─────────────────────────────────────────────────────────────────┘
                    │                           │
                    ▼                           ▼
        ┌───────────────────┐       ┌───────────────────────────┐
        │      Vercel       │       │    Hetzner Cloud Server   │
        │  (*.vercel.app)   │       │                           │
        ├───────────────────┤       │  ┌─────────────────────┐  │
        │   Parent Portal   │ ────► │  │    Nginx/Traefik    │  │
        │    (Next.js)      │       │  │   (Reverse Proxy)   │  │
        └───────────────────┘       │  └──────────┬──────────┘  │
                                    │             │             │
                                    │  ┌──────────┴──────────┐  │
                                    │  │                     │  │
                                    │  ▼                     ▼  │
                                    │ ┌────────┐   ┌────────┐   │
                                    │ │   AI   │   │ Gibbon │   │
                                    │ │Service │   │  CMS   │   │
                                    │ └────┬───┘   └────────┘   │
                                    │      │                    │
                                    │      ▼                    │
                                    │ ┌────────────────────┐    │
                                    │ │     PostgreSQL     │    │
                                    │ └────────────────────┘    │
                                    └───────────────────────────┘
```

---

## Parallel Setup Tasks

The following tasks can be done **in parallel** by different team members or in separate terminal windows:

### Track A: Hetzner Setup (CLI)

```bash
# Terminal 1: Server provisioning and setup
./scripts/deploy/setup-hetzner-server.sh <your-server-ip>
```

### Track B: Vercel Setup (CLI)

```bash
# Terminal 2: Vercel project linking
./scripts/deploy/setup-vercel-projects.sh
```

### Track C: Manual Setup (Browser)

While CLI tasks run, complete these in your browser:

1. Hetzner Cloud Console: Create firewall rules, set up backups
2. Vercel Dashboard: Configure team settings, domain preferences
3. Generate secrets: JWT keys, database passwords

---

## Hetzner Server Setup

### Step 1: Create Server

1. Log in to [Hetzner Cloud Console](https://console.hetzner.cloud/)
2. Create a new project (or use existing)
3. Create a new server:
   - **Location**: Choose nearest to your users (e.g., Nuremberg, Falkenstein)
   - **Image**: Ubuntu 22.04
   - **Type**: CX21 (2 vCPU, 4GB RAM) or higher
   - **SSH Key**: Add your public key
   - **Networking**: Enable public IPv4

4. Note the server IP address

### Step 2: Bootstrap Server

Run the automated setup script:

```bash
# Set your server IP
export SERVER_IP="your.server.ip.address"

# Run the bootstrap script
./scripts/deploy/setup-hetzner-server.sh $SERVER_IP
```

The script will:
- Update system packages
- Install Docker and Docker Compose
- Configure firewall (UFW)
- Create application directories
- Set up swap space

### Step 3: Verify Server Setup

```bash
# SSH into the server
ssh root@$SERVER_IP

# Verify Docker
docker --version
docker compose version

# Verify directories
ls -la /opt/laya/

# Check firewall
ufw status
```

---

## Backend Deployment

### Step 1: Prepare Environment File

Create the production environment file:

```bash
# Copy the example file
cp ai-service/.env.production.example ai-service/.env.production

# Edit with your production values
# IMPORTANT: Generate secure secrets!
nano ai-service/.env.production
```

**Generate secure secrets:**

```bash
# Generate JWT secret
openssl rand -base64 64

# Generate database password
openssl rand -base64 32
```

### Step 2: Create Docker Compose File

Create `docker-compose.yml` in your project root:

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:15-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-laya_ai}
      POSTGRES_USER: ${POSTGRES_USER:-laya}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-laya}"]
      interval: 10s
      timeout: 5s
      retries: 5

  ai-service:
    build:
      context: ./ai-service
      dockerfile: Dockerfile
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      - POSTGRES_HOST=postgres
      - POSTGRES_PORT=5432
      - POSTGRES_DB=${POSTGRES_DB:-laya_ai}
      - POSTGRES_USER=${POSTGRES_USER:-laya}
      - POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
      - JWT_SECRET_KEY=${JWT_SECRET_KEY}
      - JWT_ALGORITHM=${JWT_ALGORITHM:-HS256}
      - CORS_ORIGINS=${CORS_ORIGINS}
    ports:
      - "8000:8000"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3

volumes:
  postgres_data:
```

### Step 3: Deploy to Server

```bash
# Copy files to server
scp docker-compose.yml root@$SERVER_IP:/opt/laya/
scp ai-service/.env.production root@$SERVER_IP:/opt/laya/.env

# SSH into server
ssh root@$SERVER_IP

# Navigate to app directory
cd /opt/laya

# Pull and start services
docker compose pull
docker compose up -d

# Check logs
docker compose logs -f
```

### Step 4: Verify Backend

```bash
# Test health endpoint
curl http://$SERVER_IP:8000/health

# Check running containers
docker compose ps
```

---

## Vercel Frontend Deployment

### Step 1: Link Project

Run the Vercel setup script:

```bash
./scripts/deploy/setup-vercel-projects.sh
```

Or manually:

```bash
cd parent-portal
vercel link
```

### Step 2: Configure Environment Variables

In Vercel Dashboard or via CLI:

```bash
# Set production environment variables
vercel env add NEXT_PUBLIC_API_URL production
# Enter: https://your-hetzner-server.example.com

vercel env add NEXT_PUBLIC_GIBBON_URL production
# Enter: https://your-hetzner-server.example.com/gibbon
```

### Step 3: Deploy

**Option A: Automatic (Git Push)**

```bash
git push origin main
# Vercel automatically deploys on push
```

**Option B: Manual Deploy**

```bash
cd parent-portal
vercel --prod
```

### Step 4: Verify Frontend

1. Visit your deployment URL (shown after deploy)
2. Check browser console for errors
3. Test authentication flow
4. Verify API connectivity

---

## SSL/TLS Configuration

### For Hetzner Backend

**Option 1: Let's Encrypt with Traefik**

Add Traefik to your docker-compose.yml:

```yaml
services:
  traefik:
    image: traefik:v2.10
    restart: unless-stopped
    command:
      - "--api.insecure=true"
      - "--providers.docker=true"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      - "--certificatesresolvers.letsencrypt.acme.email=your-email@example.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - letsencrypt:/letsencrypt

  ai-service:
    # ... existing config ...
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.ai-service.rule=Host(`api.yourdomain.com`)"
      - "traefik.http.routers.ai-service.entrypoints=websecure"
      - "traefik.http.routers.ai-service.tls.certresolver=letsencrypt"

volumes:
  letsencrypt:
```

**Option 2: Cloudflare Tunnel (No open ports)**

See [Cloudflare Tunnel documentation](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/)

### For Vercel Frontend

SSL is automatically provided by Vercel on `*.vercel.app` domains.

---

## Verification

### Run Post-Deploy Checks

```bash
# Full verification
./scripts/deploy/post-deploy-checks.sh https://your-backend-url https://your-frontend.vercel.app
```

### Manual Verification Checklist

- [ ] Backend health endpoint responds: `curl https://api.yourdomain.com/health`
- [ ] Frontend loads without errors
- [ ] Authentication flow works (login/logout)
- [ ] API calls succeed (check browser network tab)
- [ ] SSL certificates are valid
- [ ] No console errors in browser

---

## Rollback Procedures

### Backend Rollback

```bash
# SSH into server
ssh root@$SERVER_IP

# View previous images
docker images

# Stop current containers
docker compose down

# Update docker-compose.yml to use previous image tag
# Or restore previous .env file

# Restart
docker compose up -d
```

### Frontend Rollback

**Via Vercel Dashboard:**

1. Go to your project's Deployments
2. Find the previous working deployment
3. Click the three dots menu
4. Select "Promote to Production"

**Via CLI:**

```bash
# List deployments
vercel list

# Promote specific deployment
vercel promote <deployment-url>
```

---

## Troubleshooting

### Backend Issues

**Container won't start:**

```bash
# Check logs
docker compose logs ai-service

# Check container status
docker compose ps

# Restart containers
docker compose restart
```

**Database connection errors:**

```bash
# Verify PostgreSQL is running
docker compose logs postgres

# Test connection from ai-service container
docker compose exec ai-service python -c "import psycopg2; print('OK')"
```

**JWT authentication failing:**

- Verify `JWT_SECRET_KEY` matches between AI Service and Gibbon
- Check `JWT_ALGORITHM` is consistent
- Ensure token hasn't expired

### Frontend Issues

**API calls failing:**

1. Check browser console for CORS errors
2. Verify `NEXT_PUBLIC_API_URL` is set correctly in Vercel
3. Ensure backend CORS_ORIGINS includes your Vercel domain

**Images not loading:**

1. Check `next.config.js` includes your API domain in `remotePatterns`
2. Verify image URLs are using the correct protocol (HTTPS)

**Build failures:**

```bash
# Check build logs in Vercel Dashboard
# Or run locally:
cd parent-portal
npm run build
```

### SSL Issues

**Certificate errors:**

```bash
# Check certificate status
./scripts/deploy/post-deploy-checks.sh https://your-backend-url

# For Let's Encrypt issues, check Traefik logs
docker compose logs traefik
```

---

## Maintenance

### Regular Tasks

- [ ] Monitor disk space: `df -h`
- [ ] Review logs: `docker compose logs --tail=100`
- [ ] Update containers: `docker compose pull && docker compose up -d`
- [ ] Database backups: Set up automated backups in Hetzner Console

### Security Updates

```bash
# Update system packages
apt update && apt upgrade -y

# Update Docker images
docker compose pull
docker compose up -d
```

---

## Support

For issues with this deployment:

1. Check the troubleshooting section above
2. Review logs: `docker compose logs`
3. Check Vercel deployment logs in the dashboard
4. Consult the main LAYA documentation

---

*Last updated: 2026-02-17*
