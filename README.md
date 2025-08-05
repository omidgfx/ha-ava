
# High Availability Web Service Project

## Contents

1. [Project Overview](#project-overview)  
2. [Architecture Overview](#architecture-overview)  
3. [System Components](#system-components)  
4. [Installation and Setup](#installation-and-setup)  
5. [Configuration Details](#configuration-details)  
6. [Failover and Failback Process](#failover-and-failback-process)  
7. [Health Checks and Monitoring](#health-checks-and-monitoring)  
8. [Web Application](#web-application)  
9. [Database Replication](#database-replication)  
10. [File Synchronization](#file-synchronization)  
11. [Known Issues and Troubleshooting](#known-issues-and-troubleshooting)  
12. [Bonus Features](#bonus-features)  
13. [Future Improvements](#future-improvements)  
14. [License and Credits](#license-and-credits)

## 1. Project Overview

This project aims to build a high availability (HA) web service infrastructure that ensures continuous service availability even if one of the nodes fails. It is designed to handle failover scenarios gracefully by syncing both the database and uploaded files between two nodes.

### Objectives
- Provide a reliable and fault-tolerant web service with zero downtime.
- Implement master-slave replication for MariaDB to keep the database synchronized.
- Synchronize uploaded files in real-time between the two nodes using rsync and inotify.
- Automate failover and failback processes via Keepalived with custom health checks.
- Deliver a PHP-based web application as a test platform to demonstrate HA functionality including user creation and file management.

### Key Features
- Automatic failover of virtual IP (VIP) between nodes.
- Health checks for MariaDB, rsync, file sync service, and inotify to ensure service integrity.
- Real-time synchronization of files uploaded to the web service.
- Read-only mode for the slave database node to prevent conflicts.
- Ability to promote the slave to master on failover, preserving data consistency.
- Web interface for adding random users and managing uploaded files (upload, rename, delete).

### Technologies Used
- MariaDB for database storage and replication.
- Keepalived for VIP failover management.
- Rsync and inotify-tools for real-time file synchronization.
- PHP for the web application.
- Bash scripting for automation and health checks.

This documentation details the architecture, setup, configuration, and operation of the system, providing a comprehensive guide for deploying and managing the high availability web service.

## 2. Architecture Overview

This project implements a high-availability (HA) setup for a web service using two nodes with MariaDB master-slave replication and file synchronization via rsync. The architecture ensures continuous service availability and data consistency during failover scenarios.

### Network Setup

The HA cluster consists of two network interfaces on each node, both configured as host-only networks in a virtualized environment:

| Interface | Network Type | IP Range         | Description                              |
|-----------|--------------|------------------|------------------------------------------|
| enp0s8    | Host-only    | 192.168.137.x    | Private inter-node communication network. This interface is used for backend synchronization, replication, and internal communication. Node1: 192.168.137.101, Node2: 192.168.137.102. |
| enp0s9    | Host-only    | 192.168.100.x    | Frontend network serving client traffic through a Virtual IP (VIP). Node1: 192.168.100.101, Node2: 192.168.100.102. |

**Internet Access:**  
The enp0s8 interface is connected to the host machine's internet via Windows 11 shared network, allowing both nodes to have internet access through this interface.

**Usage:**  
- `enp0s8` is dedicated to internal cluster communication, replication traffic, and file sync commands.  
- `enp0s9` is the public-facing network interface where Keepalived manages the VIP failover and client connections.

This segregation ensures that the failover and synchronization traffic remains isolated from external client traffic.

### Components

| Component           | Role                                   | Description                                                                                   |
|---------------------|----------------------------------------|-----------------------------------------------------------------------------------------------|
| Node 1 (Primary)    | Active Master                          | Hosts the master MariaDB instance and serves the virtual IP (VIP) for the web service.        |
| Node 2 (Secondary)  | Standby Slave                         | Hosts the slave MariaDB instance in read-only mode and receives file sync from Node 1.        |
| Keepalived          | VRRP-based Failover Manager           | Manages VIP failover between nodes based on health checks and service status.                  |
| MariaDB             | Database                             | Provides master-slave replication to ensure database availability and consistency.             |
| Rsync               | File Synchronization                  | Syncs uploaded files and application data in real-time between nodes.                         |
| Sync-Realtime.service| Custom File Sync Service with inotify| Watches for file system changes and triggers rsync for immediate synchronization.              |
| Web Service (PHP)   | Application Layer                     | Provides user interface for adding users and uploading files, connected to MariaDB backend.   |

### Failover Behavior

| Event                                    | Action                                                                                  |
|------------------------------------------|----------------------------------------------------------------------------------------|
| Node 1 MariaDB failure                    | Keepalived triggers failover; VIP moves to Node 2; Node 2 becomes active master.       |
| Node 1 service recovery                   | Depending on `nopreempt` setting, VIP may or may not return to Node 1 as master.        |
| File synchronization                      | Rsync and inotifywait ensure files are synced in near real-time between nodes.         |
| Database replication                      | MariaDB replication ensures data consistency; on failover, Node 2 becomes writable.    |
| Upload restrictions on inactive node     | File uploads are disabled on the node not holding the VIP (inactive).                   |

This architecture provides redundancy at both the database and file system level to minimize downtime and data loss.

## 3. System Components

This section details the main components used in the high availability web service setup, their roles, and how they interact to provide a resilient system.

| Component              | Description                                                                                       | Role in System                                         |
|------------------------|-------------------------------------------------------------------------------------------------|-------------------------------------------------------|
| **Keepalived**          | A VRRP (Virtual Router Redundancy Protocol) daemon used for managing failover of the Virtual IP. | Controls VIP failover between Node 1 and Node 2 based on health checks. |
| **MariaDB**             | Open-source relational database with built-in replication support.                               | Provides master-slave database replication to ensure data consistency and high availability. |
| **Rsync**               | Utility for efficient file synchronization between hosts.                                       | Synchronizes uploaded files and web service data between nodes in real-time or batch. |
| **inotifywait (inotify-tools)** | Linux utility to monitor filesystem events.                                                  | Detects file changes to trigger rsync for real-time synchronization. |
| **Sync-Realtime.service** | Custom systemd service running a script to monitor file changes and sync files in real-time.    | Automates file synchronization using inotify and rsync. |
| **PHP Web Application** | A simple web interface to add random users and manage uploaded files (upload, rename, delete).   | Demonstrates the HA setup functionality by interacting with the database and file system. |
| **Bash Health Check Scripts** | Custom scripts to monitor status of MariaDB, rsync, file sync service, and inotifywait.         | Used by Keepalived to determine node health and trigger failover if necessary. |

### Interaction Diagram

- Keepalived continuously runs health checks on MariaDB, rsync, sync-realtime service, and inotifywait.
- If any critical service fails, Keepalived triggers VIP failover to the standby node.
- MariaDB replication keeps slave node database up-to-date.
- Rsync and inotifywait sync uploaded files between nodes.
- The PHP application interacts with the database and file storage via the VIP address.

### Key Points

- The database is read-only on the slave node and promoted to writable during failover.
- File uploads are only accepted on the active node holding the VIP.
- Health check scripts include retry and timeout logic, and attempt automatic recovery where possible.

## 4. Installation and Setup
### Prerequisites

- Two Linux nodes (e.g., Debian or Ubuntu).
- Static IP setup on two network interfaces (`enp0s8` and `enp0s9`).
- Root access on both nodes.
- Installed packages: MariaDB, rsync, inotify-tools, keepalived, curl, Apache or Nginx with PHP.
### Step 1: Network Configuration

Each node has two host-only network interfaces:

- `enp0s8` → 192.168.137.x (for replication, sync, and internet access via host)
- `enp0s9` → 192.168.100.x (used for client traffic and VIP)

Example IPs:

| Node   | enp0s8             | enp0s9             |
|--------|--------------------|--------------------|
| Node1  | 192.168.137.101    | 192.168.100.101    |
| Node2  | 192.168.137.102    | 192.168.100.102    |

### Step 2: Package Installation

Install required packages on both nodes:

```bash
apt update
apt install mariadb-server mariadb-client rsync inotify-tools keepalived curl apache2 php libapache2-mod-php -y
```
### Step 3: MariaDB Replication User

Log in to MariaDB and run:

```sql
CREATE USER 'repl'@'%' IDENTIFIED BY 'repl_password';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
FLUSH PRIVILEGES;
```
### Step 4: Keepalived Setup

- Copy `keepalived.conf` to `/etc/keepalived/` on both nodes.
- Put all health check scripts in `/etc/keepalived/checks/`.
- Set execute permissions:

```bash
chmod +x /etc/keepalived/checks/*.sh
```

- Enable and start Keepalived:

```bash
systemctl enable keepalived
systemctl start keepalived
```
### Step 5: Web Application Deployment

- Deploy the PHP files to `/var/www/html/`.
- Ensure an `uploads` directory exists:

```bash
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
```
### Step 6: Real-time File Sync

- Create systemd service `sync-realtime.service`.
- Put your inotify-based rsync script in `/usr/local/bin/sync-realtime.sh`.
- Enable and start the service:

```bash
chmod +x /usr/local/bin/sync-realtime.sh
systemctl daemon-reexec
systemctl enable sync-realtime
systemctl start sync-realtime
```
### Step 7: Initial Sync & Replication

- Use rsync once manually to copy files:

```bash
rsync -az --delete /var/www/html/uploads/ root@node2:/var/www/html/uploads/
```

- Dump DB from master and import on slave, then start replication.
### Step 8: Testing the Setup

- Visit the VIP (e.g., http://192.168.100.200) and test web interface.
- Try creating users, uploading files.
- Stop MariaDB on Node1 and verify Node2 becomes master and web continues working.


## 5. Configuration Details

This section details the main configuration files used for the High Availability setup, including Keepalived configurations, health check scripts, and custom failover/failback automation scripts. Each component is designed to ensure service continuity and proper synchronization between nodes.

---

### 5.1 Keepalived Configuration (`/etc/keepalived/keepalived.conf`)

- **Purpose:**  
  Manage VRRP virtual IP failover between the two nodes based on service health status.

- **Key Settings:**
  - `global_defs`:  
    Defines global options such as running health check scripts as root, enabling debugging, and setting log file location.
  - `vrrp_script`:  
    Defines multiple scripts for health checks (MariaDB, Apache2, file sync services, etc.). These scripts run periodically, and their status influences the node's priority.
  - `vrrp_instance`:  
    Defines the VRRP instance controlling the VIP failover. Both nodes are configured with `state BACKUP`, a priority value, and `nopreempt` option to prevent forced preemption after recovery.
  - `track_script`:  
    Lists health check scripts that affect node priority dynamically.

- **Node Differences:**  
  - Node1 has higher priority (`100` vs `90` on Node2), meaning Node1 is preferred as MASTER.
  - Node1 runs more health checks including `chk_mysql_master` and `chk_sync`.
  - Both nodes listen on the same VIP `192.168.100.200` on interface `enp0s9`.

---

### 5.2 Notification Script (`/etc/keepalived/notify.sh`)

- **Purpose:**  
  Triggered by Keepalived on state changes (MASTER/BACKUP/FAULT).

- **Functionality:**  
  - Logs state change events with timestamps to `/var/log/keepalived_failover.log`.
  - Sends alert messages to a Telegram/Bale channel via a custom `alert.sh` script.
  - On promotion to MASTER, triggers the failback automation script (`failback-to-node1.sh`) asynchronously.

---

### 5.3 Alert Script (`/etc/keepalived/bin/alert.sh`)

- **Purpose:**  
  Send notifications to a messaging platform (Bale in this case).

- **Details:**  
  Uses curl to POST alert messages to a predefined chat group or channel using a Bot token.

---

### 5.4 Failback Automation (`/etc/keepalived/bin/failback-to-node1.sh`)

- **Purpose:**  
  When Node1 becomes MASTER again, this script:
  1. Synchronizes uploaded files from Node2 to Node1 using rsync.
  2. Dumps Node2’s MariaDB database and imports it into Node1.
  3. Resets Node1’s MariaDB master status and obtains new replication coordinates.
  4. Reconfigures Node2 as the slave, starting replication from the updated position.
  5. Sets Node2’s database to read-only to prevent conflicting writes.

- **Logging:**  
  All steps are logged into `/var/log/keepalived/failback-node1.log` for troubleshooting.

---

### 5.5 Health Check Scripts (`/etc/keepalived/checks/*.sh`)

These scripts run periodically as configured in Keepalived and perform service checks with retry and auto-fix mechanisms:

- **check_apache2.sh**  
  Checks if Apache2 service is active. If down, attempts to restart it with up to 3 retries, logging all attempts and sending alerts on failure.

- **check_mariadb.sh**  
  Verifies MariaDB server status. If inactive, tries to restart MariaDB service similarly with retries and alerts.

- **check_mariadb_master.sh**  
  Confirms the node’s MariaDB instance is acting as master by checking the absence of slave status. If detected as slave or error, alerts are sent.

- **check_sync_realtime.sh**  
  Checks the custom file synchronization service (`rsync-realtime.service`). Attempts restart on failure with logging and alerts.

- **check_inotifywait.sh**  
  Ensures the `inotifywait` process is running (required for real-time file sync). If not, attempts to restart the sync service and sends alerts on failure.

---

### 5.6 File Sync Script (`/usr/local/bin/rsync_realtime.sh`)

- **Purpose:**  
  Runs continuously, monitoring `/var/www/html` directory for changes (create, modify, delete, move) using `inotifywait`.

- **Behavior:**  
  On any file change detected, triggers an rsync to sync files immediately to the other node, setting ownership to `www-data:www-data`.

- **Initial Sync:**  
  Runs an initial rsync on startup to ensure directories are in sync.

- **Logging:**  
  Logs all file events and rsync operations to `/var/log/rsync_realtime.log`.

---

### 5.7 Node2 Promotion Script (`/etc/keepalived/bin/activate-node2-master.sh`)

- **Purpose:**  
  When Node2 takes over as MASTER (failover), this script disables slave mode on MariaDB and makes the database writable.

- **Operations:**  
  Executes SQL commands to stop and reset slave, then sets `read_only` to OFF.

- **Logging:**  
  Logs all actions in `/var/log/keepalived/promote-node2.log`.

---

### 5.8 Important Configuration Parameter: `nopreempt` and Initial BACKUP State on Both Nodes

- Both nodes are configured with `state BACKUP` in Keepalived, with Node1 priority higher than Node2.
- The `nopreempt` option is set on node1 only.

**Why?**

- Prevents automatic takeover of MASTER role by the higher priority node immediately after recovery.
- Avoids service disruption due to unnecessary failbacks.
- Ensures that failback procedures (file sync, DB promotion) run in a controlled manner only when explicitly triggered.
- Increases overall cluster stability by preventing “flapping” of the VIP between nodes.
- This pattern is recommended for HA systems prioritizing stability and data consistency over strict priority enforcement.

---

This comprehensive configuration guarantees the high availability cluster operates reliably, synchronizes data accurately, and gracefully handles failover and failback scenarios.

## 6. Failover and Failback Process
### 6.1 Failover Process

Failover is triggered automatically when Keepalived detects a failure in the primary node (Node1). The process ensures the virtual IP (VIP) and services shift seamlessly to the secondary node (Node2) to maintain availability.

Key Steps:
- Keepalived on Node1 monitors health checks for Apache2, MariaDB master status, file sync service, and inotify.
- On failure of any critical service, Keepalived lowers Node1’s priority and triggers failover.
- Node2, with the VIP configured on the shared network interface, assumes the MASTER role and the VIP.
- Node2 runs the `activate-node2-master.sh` script to promote its MariaDB to master by stopping slave replication and setting the database to writable.
- File synchronization continues via rsync and inotify from Node1 to Node2, now operating as the active node.
- The web application on Node2 becomes fully active, accepting writes and uploads.

This automated failover minimizes downtime and preserves data consistency during the primary node’s outage.

---

### 6.2 Failback Process

Failback occurs when the original primary node (Node1) recovers and is ready to resume its role as MASTER. The process ensures Node1 is fully synchronized before resuming control to avoid data loss or conflicts.

Key Steps:
- Keepalived on Node1 detects recovery and state changes to MASTER with VIP reassignment.
- The `failback-to-node1.sh` script is executed asynchronously by the Keepalived notify script.
- This script performs:
  1. Rsync from Node2 to Node1 to synchronize all uploaded files.
  2. Dumps Node2’s MariaDB database and imports it into Node1 to catch up on data changes.
  3. Resets Node1’s MariaDB master logs and obtains replication coordinates.
  4. Reconfigures Node2 as a slave with the updated master log position and sets its database to read-only.
- After synchronization, Node1 resumes full master operations and client traffic is routed back to Node1’s VIP.
- Node2 continues operating as the slave node with a read-only database to maintain consistency.

This controlled failback avoids “split-brain” scenarios and ensures seamless restoration of the original master node.

---

### 6.3 Important Notes on Failover/Failback

- **Health Checks:**  
  Multiple health checks on essential services (Apache2, MariaDB, file sync) ensure failover triggers only on real failures.

- **`nopreempt` Setting:**  
  Prevents the VIP from switching back to Node1 immediately after recovery, allowing manual or controlled failback through the failback script.

- **Synchronization Consistency:**  
  File sync and database replication mechanisms guarantee data consistency between nodes during and after failover.

- **Logging:**  
  All failover and failback operations are logged extensively for auditing and troubleshooting.

- **Alerting:**  
  Notifications are sent via messaging bots to inform administrators of failover or failback events in real time.

---
## 7. Health Checks and Monitoring
Reliable health checks are essential to ensure the high availability cluster functions correctly by detecting failures promptly and triggering failover or recovery actions.
### 7.1 Health Check Scripts

Custom health check scripts are used by Keepalived to monitor the status of critical services. Each script implements retries, timeout, and automatic fix attempts where possible.

| Script Name               | Purpose                                      | Behavior Summary                                                  |
|--------------------------|----------------------------------------------|------------------------------------------------------------------|
| `check_apache2.sh`        | Checks Apache2 web server status             | Checks if Apache2 is active; tries restart up to 3 times; logs and alerts failures. |
| `check_mariadb.sh`        | Checks MariaDB database service status       | Checks if MariaDB is active; tries restart up to 3 times; logs and alerts failures. |
| `check_mariadb_master.sh` | Verifies MariaDB node is master (not slave) | Runs SQL command to detect slave status; alerts if node is not master. |
| `check_sync_realtime.sh`  | Checks the custom rsync/inotify file sync    | Verifies if sync-realtime.service is running; restarts and retries if down. |
| `check_inotifywait.sh`    | Ensures inotifywait process is running       | Checks for inotifywait process; restarts sync service if not running. |

All scripts implement 3 retries with timeouts and attempt to fix the issue by restarting the related service before failing and notifying.

---

### 7.2 Logging

Each health check script writes detailed logs into `/var/log/keepalived/` (or configured log locations) with timestamps, success/failure info, and fix attempts.

Keepalived’s global configuration also enables debug logging and logs events to `/var/log/keepalived/keepalived.log`.

Failover notifications and alerts are logged in `/var/log/keepalived_failover.log` with timestamps for audit trail.

---

### 7.3 Alerting and Notifications

Alerts are sent through a bot messaging API to notify administrators immediately about failures or state changes.

- Alerts include failure type, affected node, and retry attempt.
- Notifications are triggered by health check scripts and Keepalived state changes.
- Notification script (`alert.sh`) uses HTTP POST to send messages to a chat channel.
- Helps in quick diagnosis and faster response to incidents.

---

### 7.4 Keepalived Configuration for Health Checks

Keepalived is configured to run these scripts periodically with parameters:

- `interval`: How often the check runs (5 seconds).
- `weight`: How much the failure affects priority.
- `fall`: Number of failed checks before considered down.
- `rise`: Number of successful checks to mark healthy again.

This setup ensures failover happens only after repeated confirmed failures to avoid flapping.

---


## 8. Web Application
A PHP-based web application serves as the user interface to demonstrate the high availability setup. It provides functionality to create users, upload files, and manage uploads seamlessly across the HA cluster.

### 8.1 Features

- Add random users to the MariaDB database.
- Upload files to the web server.
- Rename and delete uploaded files.
- All file uploads and changes are synchronized in real-time between the nodes via the file sync service.
- Application connects to the active database master to ensure data consistency.
- 
### 8.2 Implementation Details

- Written in PHP using procedural style for simplicity.
- Connects to MariaDB using standard MySQLi or PDO.
- Uploads are stored in `/var/www/html/uploads/` with appropriate permissions.
- The application ensures write operations only on the active master node.
- File operations trigger the underlying rsync + inotify-based sync service to replicate changes.
- 
### 8.3 Access and Usage

- Access the application via the virtual IP (VIP) assigned by Keepalived.
- Use provided forms to add users or manage uploaded files.
- The application reflects real-time data and files due to backend synchronization.

## 9. Database Replication

The project uses MariaDB master-slave replication to maintain data consistency and high availability between two nodes.

### 9.1 Replication Setup

- Node 1 acts as the Master database server.
- Node 2 acts as the Slave database server in read-only mode.
- Replication is asynchronous, ensuring near real-time data replication.
- Replication user (`repl`) with limited permissions is created for secure replication.
- On failover, Node 2 can be promoted to Master and Node 1 demoted to Slave to maintain availability.

### 9.2 Key Configuration Parameters

| Parameter             | Description                                         |
|-----------------------|-----------------------------------------------------|
| server-id             | Unique identifier per node (e.g., 1 for Node1, 2 for Node2) |
| log_bin               | Enables binary logging for replication             |
| binlog_format         | Set to `ROW` for detailed event logging            |
| replicate_do_db       | Specifies databases to replicate                    |
| replicate_skip_errors | Allows replication to continue on certain errors   |

### 9.3 Failover and Promotion

- During failover, the Keepalived script promotes the Slave (Node 2) to Master by:
  - Stopping slave threads.
  - Resetting slave configuration.
  - Disabling read-only mode.
- The former Master becomes Slave upon failback with updated master coordinates.
- Master status is checked via SQL command `SHOW SLAVE STATUS\G` to determine the current role.

### 9.4 Security Considerations

- Replication user credentials are stored securely and used only for replication.
- Access to MySQL root user is restricted to localhost or secured hosts.
- Password-less root access is avoided for production environments.

## 10. File Synchronization
This project ensures real-time synchronization of uploaded files between Node 1 and Node 2 to maintain data consistency during failover.
### 10.1 Overview

- Uploaded files reside in `/var/www/html/uploads/` on both nodes.
- Node 1 acts as the primary source for file changes.
- File synchronization uses `rsync` combined with `inotifywait` for event-driven updates.
- Changes in the upload directory trigger immediate syncing to the other node.
- On failback, files are synced from Node 2 back to Node 1 to ensure no data loss.
### 10.2 Components

| Component           | Role                                      |
|---------------------|-------------------------------------------|
| rsync               | Efficiently copies and synchronizes files |
| inotifywait         | Watches filesystem events (create, modify, delete, move) |
| rsync_realtime.sh   | Custom script that triggers rsync on file events |
### 10.3 rsync_realtime.sh Script

- Runs continuously on Node 1.
- Performs an initial full sync of `/var/www/html/` directory to Node 2.
- Uses `inotifywait` to monitor `/var/www/html` recursively for any file changes.
- On detecting changes, triggers `rsync` to sync updates immediately.
- Applies `--chown=www-data:www-data` to preserve file ownership on the remote server.
- Logs all synchronization events and errors to `/var/log/rsync_realtime.log`.
### 10.4 Failback Sync

- During failback to Node 1, files from Node 2 are synchronized back using `rsync --delete` to ensure exact mirroring.
- Sync operations are logged to `/var/log/keepalived/failback-node1.log`.
### 10.5 Upload Restrictions

- Only the active node holding the Virtual IP (VIP) accepts uploads.
- Inactive node’s web service disables file upload features to prevent data divergence.
- This ensures synchronization remains unidirectional from active to standby node.
## 11. Known Issues and Troubleshooting
This section lists common issues encountered in the HA setup along with their symptoms, causes, and recommended fixes.
### 11.1 Keepalived Failover Does Not Trigger

**Symptoms:**  
- VIP does not move to standby node when the primary fails.  
- No failover notification logs.

**Possible Causes:**  
- Health check scripts failing silently or not executable.  
- Incorrect permissions on scripts or Keepalived config files.  
- Network interface misconfiguration or mismatched `virtual_router_id`.

**Troubleshooting Steps:**  
- Check Keepalived logs (`/var/log/keepalived/keepalived.log`) for errors.  
- Verify all health check scripts have executable permissions (`chmod +x`).  
- Manually run health check scripts to ensure they return proper exit codes.  
- Confirm network interfaces and VRRP IDs match on both nodes.
### 11.2 MariaDB Replication Lag or Errors

**Symptoms:**  
- Slave node not receiving updates timely.  
- Replication stops with errors.

**Possible Causes:**  
- Network issues between nodes.  
- Incorrect replication user credentials or permissions.  
- Binary log files missing or inconsistent.

**Troubleshooting Steps:**  
- Check MariaDB slave status: `SHOW SLAVE STATUS\G` for error details.  
- Verify replication user and password in configuration and scripts.  
- Restart slave with `STOP SLAVE; RESET SLAVE ALL;` and reconfigure master settings.  
- Ensure binary logging is enabled on the master.
### 11.3 File Sync Failures

**Symptoms:**  
- Files missing or out of sync between nodes.  
- Rsync or inotifywait errors logged.

**Possible Causes:**  
- Rsync daemon not running or inaccessible.  
- SSH key authentication issues between nodes.  
- File permission or ownership mismatches.

**Troubleshooting Steps:**  
- Verify `rsync-realtime.service` status and restart if necessary.  
- Check SSH connectivity and key setup between nodes.  
- Inspect logs at `/var/log/keepalived/check_sync_realtime.log` and `/var/log/rsync_realtime.log`.  
- Ensure consistent user and group ownership on shared directories.
### 11.4 Alert Notifications Not Received

**Symptoms:**  
- Failover or health alerts do not arrive on messaging platform.

**Possible Causes:**  
- Incorrect bot token or chat ID in alert scripts.  
- Network restrictions blocking outbound HTTPS requests.

**Troubleshooting Steps:**  
- Test curl command manually with the bot API URL.  
- Confirm correct token and chat ID in `/etc/keepalived/bin/alert.sh`.  
- Check firewall or proxy settings on nodes.
### 11.5 Troubleshooting Tips

- Always check the relevant log files for detailed error messages.  
- Use manual command executions to isolate problems.  
- Maintain consistent configurations and permissions on both nodes.  
- Document any custom changes for future reference.
## 12. Bonus Features
This project includes additional features beyond the core high availability functionality to enhance usability and demonstrate the system's robustness.
### 12.1 Web Application for Testing

A simple PHP-based web application is deployed on both nodes to provide a test platform for HA functionality. It offers:

- User creation with randomized data to simulate database writes.
- File upload interface allowing users to upload files that get synchronized in real-time across nodes.
- Basic file management: rename and delete uploaded files.
- Immediate reflection of changes on the active node, showcasing database replication and file synchronization working together.
### 12.2 Real-Time File Synchronization Service

A custom `rsync-realtime.service` is implemented that leverages `inotifywait` to monitor filesystem changes under the web root directory. Upon detecting file modifications, creations, or deletions, it triggers rsync commands to sync files instantly between nodes. This reduces latency compared to scheduled syncs and ensures data consistency.

Key aspects:  
- Runs as a systemd service for reliability.  
- Logs activity and errors to `/var/log/rsync_realtime.log`.  
- Automatically attempts to restart on failures, integrated with keepalived health checks.
### 12.3 Notification Alerts Integration

Alerts and failover notifications are sent automatically to a messaging platform (e.g., Bale messenger) via bot API calls. This allows real-time monitoring and immediate awareness of cluster state changes without manually checking logs.

- Configured with bot token and chat ID in alert scripts.  
- Notifications include service failures, failovers, and recovery events.  
- Enhances operational transparency and response times.
### 12.4 Automated Failback and Promotion Scripts

Scripts are provided to automate complex steps during failback and failover:

- **failback-to-node1.sh:** Syncs files from Node 2 to Node 1, imports latest database dumps, resets replication roles, and promotes Node 1 to master.
- **activate-node2-master.sh:** Promotes Node 2 to master by stopping slave processes and disabling read-only mode.

These scripts reduce human errors and speed up recovery procedures.
### 12.5 Network Isolation and Host-Only Interfaces

The cluster uses two dedicated host-only network interfaces to separate internal HA traffic and external client traffic:

- `enp0s8` for backend synchronization and replication, isolated and protected from outside.
- `enp0s9` for frontend client access and VIP management.

This design improves security, reduces interference, and optimizes failover behavior.
## 13. Future Improvements
While the current high availability setup provides robust failover and synchronization capabilities, there are several areas identified for potential enhancement to improve scalability, resilience, and maintainability.
### 13.1 Multi-Node Clustering

Extend the architecture from a two-node active-standby setup to a multi-node cluster with automatic leader election and quorum management. This would improve fault tolerance by avoiding single points of failure and allowing load balancing.
### 13.2 Automated Conflict Resolution

Implement advanced file synchronization conflict detection and resolution mechanisms to handle edge cases where simultaneous changes occur on both nodes before syncing. This could involve versioning, file locking, or user notifications.
### 13.3 Secure Replication and Synchronization

Enhance security by using encrypted channels (e.g., TLS/SSL or SSH tunnels) for database replication and file synchronization traffic. Introduce stricter access controls and auditing for all sync operations.
### 13.4 Improved Monitoring and Alerting

Integrate with enterprise-grade monitoring solutions (like Prometheus, Grafana, or Zabbix) for detailed metrics, dashboards, and alerting rules. This would provide deeper insights into system health and performance.
### 13.5 Containerization and Orchestration

Containerize all components (database, web app, sync services) using Docker or similar technologies and deploy using orchestration platforms like Kubernetes. This would simplify deployment, scaling, and lifecycle management.
### 13.6 Backup and Disaster Recovery

Implement automated backup strategies with offsite replication and point-in-time recovery capabilities to protect against data corruption or catastrophic failures.
### 13.7 Web Application Enhancements

Add user authentication, role-based access control, and more comprehensive file management features to the test web application, making it suitable for real production scenarios.
### 13.8 Failover Testing Automation

Create scripts or CI/CD pipeline stages that simulate various failure scenarios (service crashes, network partitions, node shutdowns) to continuously validate the failover and failback mechanisms.
## 14. License and Credits
### License

This project is released under the MIT License. You are free to use, modify, and distribute the code with proper attribution. The full license text can be found in the LICENSE file.
### Credits

- **Project Author:** Pejman Chatrrouz  
- **Core Technologies:**  
  - MariaDB – Database management and replication  
  - Keepalived – VIP failover management  
  - Rsync & inotify-tools – Real-time file synchronization  
  - PHP – Web application development  
- **Special Thanks:**  
  - Open Source Community for all tools and libraries used  
  - Bale API team for messaging integration  
  - All contributors and testers who helped improve this project
### Contact

For questions, feedback, or contributions, please contact Pejman via GitHub or email at [Gmail](mailto:omid.quist@gmail.com).

