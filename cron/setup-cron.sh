#!/bin/bash
#
# SRP Cron Setup Helper
# This script helps setup cronjobs for SRP maintenance
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${GREEN}=== SRP Cronjob Setup ===${NC}\n"

# Detect PHP path
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo -e "${RED}Error: PHP not found in PATH${NC}"
    exit 1
fi

echo -e "PHP Path: ${GREEN}$PHP_PATH${NC}"
echo -e "Project Root: ${GREEN}$PROJECT_ROOT${NC}\n"

# Make cron scripts executable
echo -e "${YELLOW}Making cron scripts executable...${NC}"
chmod +x "$SCRIPT_DIR"/*.php
echo -e "${GREEN}✓ Done${NC}\n"

# Create backup directory
echo -e "${YELLOW}Creating backup directory...${NC}"
mkdir -p "$PROJECT_ROOT/backups"
chmod 755 "$PROJECT_ROOT/backups"
echo -e "${GREEN}✓ Created: $PROJECT_ROOT/backups${NC}\n"

# Create logs directory
echo -e "${YELLOW}Creating logs directory...${NC}"
mkdir -p "$PROJECT_ROOT/logs"
chmod 755 "$PROJECT_ROOT/logs"
echo -e "${GREEN}✓ Created: $PROJECT_ROOT/logs${NC}\n"

# Generate crontab entries
echo -e "${YELLOW}Generating crontab entries...${NC}\n"

cat << EOF > "$SCRIPT_DIR/crontab.txt"
# ===================================================================
# SRP Automated Maintenance Jobs
# Generated on $(date)
# ===================================================================

# Log cleanup - Every day at 2 AM (keep logs for 7 days)
0 2 * * * $PHP_PATH $SCRIPT_DIR/cleanup.php 7 >> $PROJECT_ROOT/logs/cron-cleanup.log 2>&1

# Health check - Every 15 minutes
*/15 * * * * $PHP_PATH $SCRIPT_DIR/health-check.php >> $PROJECT_ROOT/logs/cron-health.log 2>&1

# Database backup - Every day at 3 AM (keep backups for 30 days)
0 3 * * * $PHP_PATH $SCRIPT_DIR/backup.php 30 >> $PROJECT_ROOT/logs/cron-backup.log 2>&1

EOF

echo -e "${GREEN}✓ Crontab entries saved to: $SCRIPT_DIR/crontab.txt${NC}\n"

# Display generated crontab
echo -e "${YELLOW}Generated Crontab Entries:${NC}"
echo -e "${GREEN}─────────────────────────────────────────────────────────${NC}"
cat "$SCRIPT_DIR/crontab.txt"
echo -e "${GREEN}─────────────────────────────────────────────────────────${NC}\n"

# Ask user to install
echo -e "${YELLOW}Do you want to install these cronjobs now? (y/n)${NC}"
read -r INSTALL

if [ "$INSTALL" = "y" ] || [ "$INSTALL" = "Y" ]; then
    echo -e "\n${YELLOW}Installing cronjobs...${NC}"

    # Backup existing crontab
    crontab -l > "$SCRIPT_DIR/crontab-backup-$(date +%Y%m%d-%H%M%S).txt" 2>/dev/null || true

    # Append new jobs to existing crontab
    (crontab -l 2>/dev/null || true; cat "$SCRIPT_DIR/crontab.txt") | crontab -

    echo -e "${GREEN}✓ Cronjobs installed successfully!${NC}\n"

    echo -e "${YELLOW}Current crontab:${NC}"
    crontab -l

else
    echo -e "\n${YELLOW}Cronjobs NOT installed.${NC}"
    echo -e "To install manually, run: ${GREEN}crontab -e${NC}"
    echo -e "Then copy the contents from: ${GREEN}$SCRIPT_DIR/crontab.txt${NC}\n"
fi

# Test cron scripts
echo -e "\n${YELLOW}Would you like to test the cron scripts now? (y/n)${NC}"
read -r TEST

if [ "$TEST" = "y" ] || [ "$TEST" = "Y" ]; then
    echo -e "\n${YELLOW}Testing cleanup.php...${NC}"
    $PHP_PATH "$SCRIPT_DIR/cleanup.php" 7

    echo -e "\n${YELLOW}Testing health-check.php...${NC}"
    $PHP_PATH "$SCRIPT_DIR/health-check.php"

    echo -e "\n${YELLOW}Testing backup.php...${NC}"
    $PHP_PATH "$SCRIPT_DIR/backup.php" 30

    echo -e "\n${GREEN}✓ All tests completed!${NC}\n"
fi

# Create .htaccess for security
echo -e "${YELLOW}Creating security .htaccess files...${NC}"

# Protect cron directory
cat << 'EOF' > "$SCRIPT_DIR/.htaccess"
# Deny web access to cron scripts
Order Deny,Allow
Deny from all
EOF

# Protect backup directory
cat << 'EOF' > "$PROJECT_ROOT/backups/.htaccess"
# Deny web access to backups
Order Deny,Allow
Deny from all
EOF

# Protect logs directory
cat << 'EOF' > "$PROJECT_ROOT/logs/.htaccess"
# Deny web access to logs
Order Deny,Allow
Deny from all
EOF

echo -e "${GREEN}✓ Security .htaccess files created${NC}\n"

echo -e "${GREEN}=== Setup Complete! ===${NC}\n"

echo -e "${YELLOW}Next Steps:${NC}"
echo -e "1. Check crontab: ${GREEN}crontab -l${NC}"
echo -e "2. Monitor logs: ${GREEN}tail -f $PROJECT_ROOT/logs/cron-*.log${NC}"
echo -e "3. Review guide: ${GREEN}less $PROJECT_ROOT/CRONJOB_GUIDE.md${NC}\n"

echo -e "${YELLOW}Useful Commands:${NC}"
echo -e "  List crons:   ${GREEN}crontab -l${NC}"
echo -e "  Edit crons:   ${GREEN}crontab -e${NC}"
echo -e "  Remove crons: ${GREEN}crontab -r${NC}"
echo -e "  View logs:    ${GREEN}tail -f $PROJECT_ROOT/logs/cron-cleanup.log${NC}\n"
