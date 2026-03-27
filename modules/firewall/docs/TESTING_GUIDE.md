# Firewall Monitor - Testing Guide

## 🧪 Testing Setup

### Option 1: Test on Your Personal Server (Recommended)

You can safely test the system on your personal server using simple commands that won't break anything.

### Option 2: Use Localhost for Testing

Test with localhost IPs (127.0.0.1) to verify the system works without affecting real networks.

---

## 📋 Step-by-Step Testing Instructions

### Step 1: Set Up Test Site

1. **Add a Test Site** in the dashboard:
   - **Site Name**: `Test Server`
   - **Location**: `Local Testing`
   - **Primary IP**: `127.0.0.1` (localhost - always responds to ping)
   - **Secondary IP**: `127.0.0.1` (same for testing)
   - **SSH Username**: Your server username
   - **SSH Password**: Your server password
   - **Ping Count**: `5` (for faster testing)
   - **Ping Interval**: `2` (2 seconds - faster testing)

### Step 2: Test Commands

Use these **SAFE** commands that just echo output (won't break anything):

#### **Failover Command** (Safe Test):
```bash
echo "FAILOVER: Primary connection disabled, switching to secondary" && date && whoami
```

#### **Failback Command** (Safe Test):
```bash
echo "FAILBACK: Restoring primary connection" && date && whoami
```

#### **Reboot Command** (Safe Test - DOES NOT REBOOT):
```bash
echo "REBOOT: Would reboot firewall (test mode)" && date && uptime
```

**⚠️ IMPORTANT**: These commands are safe and won't actually change network settings or reboot anything.

---

## 🎯 Testing Scenarios

### Test 1: Manual Actions (Safest)

1. **Go to Dashboard**
2. **Click "Failover"** button on your test site
   - Should connect via SSH
   - Execute the failover command
   - Show output in logs
   - Send email notification

3. **Click "Fail Back"** button
   - Should execute failback command
   - Log the action

4. **Click "Reboot"** button
   - Should execute reboot command
   - Log the action

5. **Check Logs page** - Should see all actions recorded

### Test 2: Ping Monitoring (Safe)

1. **Add a site with a real IP** that you know responds to ping:
   - Primary IP: `8.8.8.8` (Google DNS - always up)
   - Set ping count: `3`
   - Set interval: `2`
   - Enable monitoring

2. **Start monitor.php**:
   ```bash
   php monitor.php
   ```

3. **Watch the output** - Should show:
   - Ping attempts
   - Status updates
   - Should show "UP" status

4. **Check dashboard** - Status should be GREEN (UP)

### Test 3: Simulate Failure (Advanced)

To test automatic failover, you need an IP that will fail:

**Option A: Use a non-existent IP**
- Primary IP: `192.168.255.255` (unlikely to exist)
- This will always fail ping
- System should trigger auto-failover

**Option B: Temporarily block ping on your server**
```bash
# On your server, temporarily block ICMP (ping)
sudo iptables -A INPUT -p icmp --icmp-type echo-request -j DROP

# Test the system

# When done, remove the rule:
sudo iptables -D INPUT -p icmp --icmp-type echo-request -j DROP
```

---

## 🔧 Real Commands for Production

When you're ready for production, replace test commands with real firewall commands:

### For Check Point Firewall:
```bash
# Failover
clish -c "set internet-connection Primary disable" && clish -c "set internet-connection Secondary enable"

# Failback  
clish -c "set internet-connection Primary enable" && clish -c "set internet-connection Secondary disable"

# Reboot
reboot force
```

### For Fortinet Firewall:
```bash
# Failover
config system interface
edit "wan1"
set status down
next
edit "wan2"
set status up
end

# Failback
config system interface
edit "wan1"
set status up
next
edit "wan2"
set status down
end

# Reboot
execute reboot
```

### For Generic Linux Router:
```bash
# Failover
ip route del default via PRIMARY_GATEWAY
ip route add default via SECONDARY_GATEWAY

# Failback
ip route del default via SECONDARY_GATEWAY
ip route add default via PRIMARY_GATEWAY

# Reboot
reboot
```

---

## ✅ Testing Checklist

- [ ] Can add a new site
- [ ] Can edit a site
- [ ] Can delete a site
- [ ] Manual "Failover" button works
- [ ] Manual "Fail Back" button works
- [ ] Manual "Reboot" button works
- [ ] Status shows correctly (Green/Yellow/Red)
- [ ] Ping monitoring works (monitor.php running)
- [ ] Email notifications sent
- [ ] Logs page shows all actions
- [ ] Can add/delete notification emails
- [ ] PIN authentication works
- [ ] Settings page works (change defaults)

---

## 🚀 Quick Start Testing

1. **Start the monitoring script**:
   ```bash
   cd /path/to/firewall-monitor
   php monitor.php
   ```
   Keep this running in a terminal.

2. **In another terminal, start the web server**:
   ```bash
   php -S localhost:8000
   ```

3. **Open browser**: `http://localhost:8000/index.php`

4. **Enter PIN**: `4683` (or your configured PIN)

5. **Add a test site** with safe commands

6. **Test manual actions** first

7. **Then test monitoring** with a known-good IP

---

## ⚠️ Safety Notes

- **Test commands are safe** - they only echo text, don't change anything
- **Use localhost (127.0.0.1)** for initial testing
- **Use known-good IPs** (like 8.8.8.8) for ping testing
- **Don't use real firewall IPs** until you're confident
- **Test on a separate network** if possible
- **Have a backup plan** if testing on production equipment

---

## 🐛 Troubleshooting

### SSH Connection Fails
- Check SSH is enabled on server: `sudo systemctl status ssh`
- Test manually: `ssh username@your-server-ip`
- Check firewall allows SSH (port 22)

### Ping Always Fails
- Check if ping is allowed: `ping 8.8.8.8`
- Some servers block ping - use a known-good IP
- Check firewall rules

### Monitor.php Not Working
- Check PHP has exec() enabled
- Test ping manually: `ping -c 5 8.8.8.8`
- Check database connection
- Check file permissions

### Email Not Sending
- Check SMTP server is accessible
- Test with a simple PHP mail() script
- Check spam folder
- Verify email addresses are added

---

## 📞 Need Help?

If something doesn't work:
1. Check the logs page for error messages
2. Check monitor.php output for errors
3. Verify database connection
4. Test SSH connection manually
5. Test ping manually

