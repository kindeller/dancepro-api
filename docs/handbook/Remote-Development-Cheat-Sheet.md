# Remote Development Cheat Sheet

## I'm on another laptop and want to work on DancePro — what do I do?

On the main development PC:

1. Turn on the PC and start Docker Desktop.
2. Open WSL and ensure SSH is running:

   ```bash
   sudo service ssh status
   sudo service ssh start
   ```

On the laptop:

1. Open VS Code.
2. Run **Remote-SSH: Connect to Host**.
3. Connect to:

   ```text
   <wsl-user>@<development-pc-lan-ip>
   ```

4. Open the DancePro V2 folder in the remote WSL filesystem.
5. In the VS Code terminal, start or check Sail:

   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail ps
   ```

6. Open the application in a laptop browser:

   ```text
   http://<development-pc-lan-ip>:<application-port>
   ```

To disconnect, close the remote VS Code window or use **Remote: Close Remote
Connection**. Sail runs detached and can remain running after disconnection.

## If It Doesn't Work

Check in this order:

1. Can the laptop SSH to the development PC?

   ```bash
   ssh <wsl-user>@<development-pc-lan-ip>
   ```

2. Is WSL running? Open it on the main PC.
3. Is `sshd` running inside WSL?

   ```bash
   sudo service ssh status
   sudo service ssh start
   sudo ss -tlnp | grep :22
   ```

4. Does Docker work inside WSL?

   ```bash
   docker ps
   ```

5. Does Sail work from the project root?

   ```bash
   ./vendor/bin/sail ps
   ./vendor/bin/sail up -d
   ```

6. If Docker broke after WSL or networking changes, run this in Windows
   PowerShell, completely restart Docker Desktop, then reopen WSL:

   ```powershell
   wsl --shutdown
   ```

For setup details and deeper troubleshooting, see the
[Remote Development guide](Remote-Development.md).

