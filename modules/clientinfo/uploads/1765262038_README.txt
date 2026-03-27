To set up an OpenVPN client on Windows:

1. Install OpenVPN client on Windows
   *An OpenVPN client on Windows is called OpenVPN GUI. 
   *Download it from http://openvpn.net/index.php/open-source/downloads.html and install the client. 
   *The default installation directory is C:\ProgramFiles\OpenVPN.

2. Run OpenVPN GUI as administrator. 

3. Edit VPNConfig.ovpn and replace YOUR_SERVER_IP with public IP of your DiskStation.
   *If your DiskStation is behind a router, replace YOUR_SERVER_IP with the router's IP.
   *Remove # before "redirect-gateway def1" to route all client traffic (including web-traffic) through this VPN Server.

4. Put VPNConfig.ovpn into the config subdirectory under OpenVPN directory
   (ie. C:\Program Files\OpenVPN\config\).

==============================================================================

To set up an OpenVPN client on Mac:

1. Install OpenVPN client on MAC
   *An OpenVPN client on Mac OS X is called Tunnelblick. 
   *Download it from http://code.google.com/p/tunnelblick/ and install the client.

2. Launch Tunnelblick. 

3. Click Create and open configuration folder button; a Finder window will appear with the configuration folder. 

4. Edit VPNConfig.ovpn and replace YOUR_SERVER_IP with public IP of your DiskStation.
   *If your DiskStation is behind a router, replace YOUR_SERVER_IP with the router's IP.
   *Remove # before "redirect-gateway def1" to route all client traffic (including web-traffic) through this VPN Server.

5. Put the files of VPNConfig.ovpn into the configuration folder. 

==============================================================================

To set up an OpenVPN client on Linux:

Please refer to the official documentation provided by 
OpenVPN http://openvpn.net/index.php/open-source/documentation.html for more information.
