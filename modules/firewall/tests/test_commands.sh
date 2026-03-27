#!/bin/bash
# Safe Test Commands for Firewall Monitor
# These commands are safe to use - they don't change anything, just echo output

echo "=========================================="
echo "Firewall Monitor - Safe Test Commands"
echo "=========================================="
echo ""

echo "1. FAILOVER Command (Safe Test):"
echo "   Command: echo 'FAILOVER: Primary connection disabled, switching to secondary' && date && whoami"
echo "   Output:"
echo 'FAILOVER: Primary connection disabled, switching to secondary' && date && whoami
echo ""

echo "2. FAILBACK Command (Safe Test):"
echo "   Command: echo 'FAILBACK: Restoring primary connection' && date && whoami"
echo "   Output:"
echo 'FAILBACK: Restoring primary connection' && date && whoami
echo ""

echo "3. REBOOT Command (Safe Test - DOES NOT REBOOT):"
echo "   Command: echo 'REBOOT: Would reboot firewall (test mode)' && date && uptime"
echo "   Output:"
echo 'REBOOT: Would reboot firewall (test mode)' && date && uptime
echo ""

echo "=========================================="
echo "These commands are safe to use in testing"
echo "They only display output, don't change anything"
echo "=========================================="

