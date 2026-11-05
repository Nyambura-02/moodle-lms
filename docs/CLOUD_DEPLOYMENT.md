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
1. Inside your Railway project canvas, click **+ Create** or **+ New Service**.
2. Select **Database** &rarr; **Add MySQL** (or **Add PostgreSQL**).
3. Railway will provision your database in seconds.

### Step 3: Link Database to the Moodle Service
1. Click your `moodle-lms` service &rarr; open the **Variables** tab.
2. Click **New Variable** &rarr; **Add Reference**:
   * Add `DATABASE_URL` linking to `${{ MySQL.DATABASE_URL }}` (or select from dropdown).
   * Or add:
     * `MYSQLHOST`: `${{ MySQL.MYSQLHOST }}`
     * `MYSQLPORT`: `${{ MySQL.MYSQLPORT }}`
     * `MYSQLUSER`: `${{ MySQL.MYSQLUSER }}`
     * `MYSQLPASSWORD`: `${{ MySQL.MYSQLPASSWORD }}`
     * `MYSQLDATABASE`: `${{ MySQL.MYSQLDATABASE }}`
3. Add a public domain:
   * Go to **Settings** &rarr; **Networking** &rarr; **Generate Domain** (e.g. `globecon-lms.up.railway.app`).

### Step 4: Done!
Railway will automatically build the Dockerfile, run database connections, and your LMS will be live on your HTTPS domain.

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
| `DATABASE_URL` | Complete connection string from Railway/Render | Auto-detected |
| `DB_TYPE` | Database driver (`mariadb`, `mysqli`, `pgsql`) | `mariadb` / `pgsql` |
| `DB_HOST` | Database host | `database` |
| `DB_PORT` | Database port | `3306` or `5432` |
| `DB_NAME` | Database schema name | `moodle` |
| `DB_USER` | Database username | `moodleuser` |
| `DB_PASSWORD` | Database password | Empty |
| `MOODLE_URL` | Public HTTPS domain URL | Auto-detected |
| `DEFAULT_THEME`| Initial active theme | `globecon` |
