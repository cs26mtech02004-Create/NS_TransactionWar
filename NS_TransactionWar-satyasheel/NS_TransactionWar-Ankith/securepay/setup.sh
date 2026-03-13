#!/bin/bash
# FILE: setup.sh
# PURPOSE: One-command setup for SecurePay.
# Run this once after cloning the project.
#
# WHAT IT DOES:
#   1. Checks Docker and Docker Compose are installed
#   2. Creates .env from .env.example if it doesn't exist
#   3. Creates required host directories (logs/, private_uploads/)
#   4. Builds and starts all containers
#   5. Waits for MySQL to be ready
#   6. Prints setup instructions for creating the admin account
#
# USAGE:
#   chmod +x setup.sh
#   ./setup.sh

set -e  # Exit immediately if any command fails

# ── COLOURS ──────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
AMBER='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No colour

echo ""
echo -e "${GREEN}╔══════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     SECUREPAY SETUP SCRIPT           ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════╝${NC}"
echo ""

# ── CHECK DOCKER ─────────────────────────────────────────────
echo -e "${CYAN}[1/5] Checking dependencies...${NC}"

if ! command -v docker &> /dev/null; then
    echo -e "${RED}ERROR: Docker is not installed.${NC}"
    echo "Install from: https://docs.docker.com/get-docker/"
    exit 1
fi

if ! docker compose version &> /dev/null && ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}ERROR: Docker Compose is not installed.${NC}"
    echo "Install from: https://docs.docker.com/compose/install/"
    exit 1
fi

echo -e "${GREEN}✓ Docker found: $(docker --version)${NC}"

# ── .ENV FILE ─────────────────────────────────────────────────
echo ""
echo -e "${CYAN}[2/5] Checking .env file...${NC}"

if [ ! -f ".env" ]; then
    cp .env.example .env
    echo -e "${AMBER}⚠  Created .env from .env.example${NC}"
    echo -e "${AMBER}⚠  IMPORTANT: Open .env and set strong passwords before continuing!${NC}"
    echo ""
    echo "    nano .env   (or use any text editor)"
    echo ""
    echo -e "${RED}Press ENTER after you have edited .env with real passwords...${NC}"
    read -r
else
    echo -e "${GREEN}✓ .env file found${NC}"
fi

# Check .env is not using placeholder values
if grep -q "REPLACE_WITH" .env; then
    echo -e "${RED}ERROR: .env still contains placeholder values.${NC}"
    echo "Edit .env and replace all REPLACE_WITH_... values with real passwords."
    exit 1
fi

# ── HOST DIRECTORIES ──────────────────────────────────────────
echo ""
echo -e "${CYAN}[3/5] Creating required directories...${NC}"

mkdir -p logs private_uploads
echo -e "${GREEN}✓ logs/ and private_uploads/ ready${NC}"

# ── BUILD AND START ───────────────────────────────────────────
echo ""
echo -e "${CYAN}[4/5] Building and starting containers...${NC}"
echo "(This may take a few minutes on first run)"
echo ""

# Use docker compose (v2) or docker-compose (v1) — whichever is available
if docker compose version &> /dev/null 2>&1; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

$COMPOSE up --build -d

# ── WAIT FOR MYSQL ─────────────────────────────────────────────
echo ""
echo -e "${CYAN}[5/5] Waiting for MySQL to be ready...${NC}"

for i in $(seq 1 30); do
    if $COMPOSE exec -T db mysqladmin ping -h localhost --silent 2>/dev/null; then
        echo -e "${GREEN}✓ MySQL is ready${NC}"
        break
    fi
    echo "  Waiting... ($i/30)"
    sleep 2
done

# ── DONE ──────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  SecurePay is running!                           ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  App:         ${CYAN}http://localhost:8080${NC}"
echo -e "  Admin panel: ${CYAN}http://localhost:8080/admin_login.php${NC}"
echo ""
echo -e "${AMBER}══ CREATE ADMIN ACCOUNT (do this once) ══════════════${NC}"
echo ""
echo "  Step 1: Generate a password hash:"
echo -e "  ${CYAN}docker exec securepay_app php -r \"echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]);\"${NC}"
echo ""
echo "  Step 2: Insert admin into DB (replace HASH with output from Step 1):"
echo -e "  ${CYAN}docker exec -it securepay_db mysql -u root -p${NC}"
echo ""
echo "  Then in MySQL:"
echo -e "  ${CYAN}USE securepay; INSERT INTO admins (username, password_hash) VALUES ('admin', 'HASH');${NC}"
echo ""
echo -e "${AMBER}══ USEFUL COMMANDS ═══════════════════════════════════${NC}"
echo ""
echo "  Stop:              $COMPOSE down"
echo "  View logs:         $COMPOSE logs -f"
echo "  Restart app only:  $COMPOSE restart app"
echo "  Full reset (wipes DB): $COMPOSE down -v"
echo ""