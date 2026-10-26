# 🚀 Deployment Guide - Upload to cPanel

## One-Time Setup (5 minutes)

### Step 1: Create Subdomain in cPanel
1. Login to your cPanel
2. Go to **Domains** → **Subdomains** (or **Domains**)
3. Create subdomain: `leadcrm`
4. Set **Document Root** to:
   ```
   /home/YOUR_CPANEL_USER/leadcrm.domain.in/public
   ```
   ⚠️ Make sure the document root points to the `/public` folder!

### Step 2: Create Database in cPanel
1. Go to **MySQL Databases**
2. Create a new database (e.g., `cpaneluser_leadcrm`)
3. Create a new database user with a strong password
4. Click **Add User to Database** → Select **ALL PRIVILEGES** → Click **Make Changes**
5. Note down:
   - Database name: `cpaneluser_leadcrm`
   - Database user: `cpaneluser_dbuser`
   - Database password: `your_password`

### Step 3: Upload Files
**Option A: Via File Manager (Easiest)**
1. Zip your entire `lead-backend` folder on your PC
2. Go to cPanel → **File Manager**
3. Navigate to `/home/YOUR_CPANEL_USER/leadcrm.domain.in/`
4. Upload the zip file
5. Extract it
6. Make sure files are directly inside `leadcrm.domain.in/` (not in a subfolder)
7. **DELETE the `.env` file** if it got uploaded (so the installer triggers)

**Option B: Via Git (If SSH available)**
```bash
cd /home/YOUR_CPANEL_USER/
git clone https://github.com/aakarsh8502etlab/lead-backend.git leadcrm.domain.in
cd leadcrm.domain.in
composer install --optimize-autoloader --no-dev
```

### Step 4: Set Permissions (via File Manager or SSH)
Right-click → Change Permissions:
- `storage/` → **755** (recursive)
- `bootstrap/cache/` → **755** (recursive)

### Step 5: Run the Installer
1. Open your browser
2. Go to: `https://leadcrm.domain.in/install.php`
3. Fill in:
   - **Database Host**: `localhost`
   - **Database Name**: The one you created in Step 2
   - **Database User**: The user you created in Step 2
   - **Database Password**: The password from Step 2
   - **API URL**: `https://leadcrm.domain.in`
   - **Admin Email**: Your admin email
   - **Admin Password**: Your admin password
4. Click **Install**
5. Done! 🎉

### Step 6: DELETE install.php ⚠️
After successful installation, **immediately delete** `install.php` from:
```
/home/YOUR_CPANEL_USER/leadcrm.domain.in/public/install.php
```
This is a **security risk** if left on the server!

---

## Folder Structure on cPanel

```
/home/YOUR_CPANEL_USER/
└── leadcrm.domain.in/          ← Full Laravel project
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/                  ← Subdomain document root points HERE
    │   ├── index.php
    │   ├── install.php          ← DELETE after installation!
    │   └── .htaccess
    ├── routes/
    ├── storage/                 ← Must be writable (755)
    ├── vendor/
    └── .env                     ← Created by installer
```

---

## What the Installer Does Automatically

✅ Tests database connection
✅ Creates database if it doesn't exist
✅ Generates `.env` with your settings
✅ Sets directory permissions
✅ Creates all 11 database tables
✅ Creates your admin account
✅ Seeds sample data (optional)
✅ Configures `.htaccess` with HTTPS redirect
✅ Sets up CORS for your frontend
✅ Clears all caches

---

## After Installation

### Your API will be live at:
```
https://leadcrm.domain.in/api
```

### Test it:
```
POST https://leadcrm.domain.in/api/login
{
  "email": "your-admin@email.com",
  "password": "your-password"
}
```

### Give this to your frontend team:
- API URL: `https://leadcrm.domain.in/api`
- See `FRONTEND_INTEGRATION_GUIDE.md` for complete integration docs

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 500 Error after install | Check `storage/logs/laravel.log` via File Manager |
| Installer won't load | Make sure subdomain document root points to `/public` |
| "No input file specified" | Document root is wrong - must point to `/public` folder |
| Database connection refused | Double-check DB name/user/password in cPanel |
| CORS errors on frontend | Re-run installer or manually edit `config/cors.php` |
| Token not working | Check `.htaccess` has the Authorization header rule |
| Blank page | Set `APP_DEBUG=true` temporarily in `.env` to see errors |

