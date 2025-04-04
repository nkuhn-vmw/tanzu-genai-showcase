#!/bin/bash

# Color codes for terminal output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Fixing file watcher limit issue...${NC}"

# Check current limits
CURRENT_LIMIT=$(cat /proc/sys/fs/inotify/max_user_watches)
echo -e "Current file watcher limit: ${CURRENT_LIMIT}"

# Temporarily fix the issue
echo -e "${GREEN}Setting temporary file watcher limit...${NC}"
echo "fs.inotify.max_user_watches=524288" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

# Verify new limit
NEW_LIMIT=$(cat /proc/sys/fs/inotify/max_user_watches)
echo -e "New file watcher limit: ${NEW_LIMIT}"

echo -e "${GREEN}File watcher limit increased successfully.${NC}"
echo -e "${YELLOW}This change will persist until the next system reboot.${NC}"
echo -e "To make this change permanent, add the following line to /etc/sysctl.conf:"
echo -e "fs.inotify.max_user_watches=524288"
