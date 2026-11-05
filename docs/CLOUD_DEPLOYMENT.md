# Deploying GlobeCon LMS to Cloud PaaS (Railway & Render)

This guide walks you through deploying the GlobeCon LMS directly from GitHub to **Railway** or **Render** in just a few minutes.

---

## Deploying on Railway (Recommended • Quickest Setup)

Railway provides 1-click database plugins, native Docker deployment, and automatic environment variable injection.

### Step 1: Sign In & Create a Project
1. Go to [railway.app](https://railway.app) and sign in with your GitHub account.
2. Click **New Project** &rarr; **Deploy from GitHub repo**.
3. Select your repository: `Nyambura-02/moodle-lms`.

### Step 2: Add a Database
1. Inside your Railway project canvas, click **+ Create** (or **+ New Service**).
2. Select **Database** &rarr; **Add MySQL** (or **Add PostgreSQL**).
3. Railway will provision your database in seconds.

### Step 3: Link Database to the Moodle Service
1. Click your `moodle-lms` web service &rarr; open the **Variables** tab.
2. Click **New Variable** &rarr; **Add Reference**:
   * Add `MYSQL_URL` referencing `${{ MySQL.MYSQL_URL }}` (or select `MYSQL_URL` from the dropdown).
   * Or add `DATABASE_URL` referencing `${{ MySQL.MYSQL_URL }}`.
3. Add a public domain:
   * Go to **Settings** &rarr; **Networking** &rarr; **Generate Domain** (e.g. `globecon-lms.up.railway.app`).

### Step 4: Done!
Railway will automatically redeploy with the linked database variables. The container startup engine will detect the connection, generate `config.php`, and initialize your LMS.

---

## Troubleshooting: "error: Database connection failed"

If your cloud URL shows:
```
error: Database connection failed
It is possible that the database is overloaded or otherwise not running properly.
The site administrator should also check that the database details have been correctly specified in config.php
```

### Why this happens:
The Moodle web container is up and running, but it cannot reach a database because either:
1. **No MySQL service has been added yet** in your Railway/Render project canvas.
2. **The database variables were not referenced** in the web service, so the web container doesn't know the private cloud address of the database.

### The 2-Step Fix:
1. **Verify the MySQL Service Exists**:
   - In Railway canvas, check if you see both `moodle-lms` and `MySQL` cards. If not, click **+ Create** &rarr; **Database** &rarr; **Add MySQL**.
2. **Reference the Database Variable in Moodle**:
   - Click the `moodle-lms` service.
   - Go to the **Variables** tab.
   - Click **New Variable** &rarr; **Add Reference**.
   - Select `MYSQL_URL` from the MySQL service (or create a variable named `MYSQL_URL` with value `${{MySQL.MYSQL_URL}}`).
   - Railway will trigger an instant automatic redeploy!
   - In **Deployments** &rarr; **View Logs**, you will see:
     ```
     [✓] Database port is OPEN and responding!
     [✓] Successfully written production config.php
     ```

---

## Deploying on Render (Blueprint Deployment)

Render allows 1-click deployment using the included [`render.yaml`](../render.yaml) blueprint.

### Step 1: Sign in to Render
1. Go to [render.com](https://render.com) and sign in with your GitHub account.
2. Click **New +** &rarr; **Blueprint**.

### Step 2: Connect Repository
1. Select your repository: `Nyambura-02/moodle-lms`.
2. Render will automatically detect `render.yaml` and configure:
   * **Web Service**: Dockerized Moodle 4.5 LMS with custom GlobeCon theme.
   * **Database**: Managed PostgreSQL database.
   * **Disk**: 10GB persistent disk volume for `moodledata`.

### Step 3: Click Apply
1. Click **Apply** to start provisioning.
2. Once the build completes, your LMS is live at `https://globecon-moodle-lms.onrender.com`.

---

## Environment Variables Reference

| Variable | Description | Default |
| :--- | :--- | :--- |
| `MYSQL_URL` / `DATABASE_URL` | Complete connection string from Railway/Render | Auto-detected |
| `DB_TYPE` | Database driver (`mariadb`, `mysqli`, `pgsql`) | `mariadb` / `pgsql` |
| `DB_HOST` | Database host | `database` |
| `DB_PORT` | Database port | `3306` or `5432` |
| `DB_NAME` | Database schema name | `railway` / `moodle` |
| `DB_USER` | Database username | `root` / `moodleuser` |
| `DB_PASSWORD` | Database password | Empty |
| `MOODLE_URL` | Public HTTPS domain URL | Auto-detected from Railway/Render |
| `DEFAULT_THEME`| Initial active theme | `globecon` |
| `MOODLE_ADMIN_PASSWORD` | Initial admin password for empty DB auto-install | `Admin@GlobeCon2026!` |
