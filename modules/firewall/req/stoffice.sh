#!/usr/bin/expect -f

# ======= CONFIGURE =======
set timeout 10
set host "172.16.90.1"
set user "admin"
set pass "www!19Kp"
# ==========================

spawn ssh $user@$host

expect {
    "yes/no" { send "yes\r"; exp_continue }
    "password:" { send "$pass\r" }
}


expect {
    -re ">" {}
    timeout { puts "No prompt after login, exiting"; exit 1 }
}

# Send reboot (force variant; change if needed)
send "reboot force\r"

expect eof


