# 🎉 Lead CRM Backend - Successfully Deployed!

## ✅ Deployment Complete

Your Laravel backend API is now **live and working** at:

```
https://leadcrm.vpsappserverone.in/api
```

---

## 🔐 Cleanup Required

**IMPORTANT:** Delete these files from your server immediately (security risk):

1. **`public/install.php`** - Installation script (no longer needed)
2. **`debug.php`** - Debug script (if exists)
3. **`test.php`** - Test script (if exists)

**How to delete via cPanel:**
- Go to **cPanel → File Manager**
- Navigate to `leadcrm.vpsappserverone.in/public/`
- Delete `install.php`
- Go back to root `leadcrm.vpsappserverone.in/`
- Delete `debug.php` and `test.php` if they exist

---

## 🚀 Your API Endpoints

### Base URL
```
https://leadcrm.vpsappserverone.in/api
```

### Available Endpoints

#### Public (No Auth Required)
- **GET** `/api` - Health check / API status
- **POST** `/api/login` - User login
- **POST** `/api/register` - User registration

#### Protected (Auth Required - Bearer Token)
- **GET** `/api/me` - Get current user info
- **POST** `/api/logout` - Logout user
- **GET** `/api/user` - Get authenticated user

---

## 🧪 Test Your API

### 1. Health Check (Browser)
Open in browser:
```
https://leadcrm.vpsappserverone.in/api
```

Should return:
```json
{
  "app": "Lead Management CRM API",
  "status": "running",
  "version": "1.0.0",
  "timestamp": "2026-03-05 ..."
}
```

### 2. Login (Postman/cURL)

**Using cURL:**
```bash
curl -X POST https://leadcrm.vpsappserverone.in/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@crm.com",
    "password": "password"
  }'
```

**Using Postman:**
- Method: `POST`
- URL: `https://leadcrm.vpsappserverone.in/api/login`
- Headers:
  - `Content-Type: application/json`
  - `Accept: application/json`
- Body (raw JSON):
```json
{
  "email": "admin@crm.com",
  "password": "password"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@crm.com",
    "role": "Admin",
    ...
  },
  "token": "1|abc123xyz..."
}
```

### 3. Get User Info (Protected Route)

**Using the token from login:**
```bash
curl -X GET https://leadcrm.vpsappserverone.in/api/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

---

## 👥 Test Credentials

If you loaded sample data during installation:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@crm.com | password |
| **Manager** | sarah@crm.com | password |
| **Sales Agent** | john@crm.com | password |
| **Sales Agent** | emily@crm.com | password |

---

## 📱 Frontend Integration

Share this information with your frontend team:

### API Base URL
```
https://leadcrm.vpsappserverone.in/api
```

### Documentation
See the complete integration guide in:
- `FRONTEND_INTEGRATION_GUIDE.md` - Complete frontend integration guide
- `API_DOCUMENTATION.md` - Detailed API documentation

---

## 🔧 Server Configuration

Your deployment uses:

- **Document Root**: `/home/USER/leadcrm.vpsappserverone.in/` (project root)
- **Public Folder**: `/home/USER/leadcrm.vpsappserverone.in/public/`
- **Laravel Version**: 8.x
- **PHP Version**: 7.4+
- **Database**: MySQL (configured via installer)

### Special Configuration
Since your document root points to the project root (not `/public`), the following fixes are in place:
- Root `.htaccess` - Rewrites requests to `public/` folder
- Root `index.php` - Proxies requests to `public/index.php`
- `public/index.php` - Strips `/public` prefix from URLs

---

## 🛠️ Maintenance Commands

Run these via **cPanel → Terminal** or SSH when needed:

### Clear Cache
```bash
cd ~/leadcrm.vpsappserverone.in
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Rebuild Cache (Production)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Check Routes
```bash
php artisan route:list
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

---

## 🔒 Security Checklist

- [x] API is running with HTTPS
- [ ] Delete `public/install.php` (DO THIS NOW!)
- [ ] Change default admin password from "password"
- [ ] Update `.env` with strong `APP_KEY`
- [ ] Set `APP_DEBUG=false` in production
- [ ] Restrict CORS origins in `config/cors.php`
- [ ] Set up regular database backups
- [ ] Monitor `storage/logs/` for errors

---

## 📊 Database Tables

All tables are created and ready:

1. `users` - User authentication & management
2. `lead_sources` - Lead sources (8 pre-populated)
3. `leads` - Lead management
4. `followups` - Follow-up tasks
5. `call_logs` - Call history
6. `notes` - Lead notes
7. `customers` - Converted customers
8. `personal_access_tokens` - Sanctum API tokens
9. `password_resets` - Password reset tokens
10. `failed_jobs` - Job queue failures
11. `migrations` - Laravel migration tracking

---

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| 404 on API routes | Clear route cache: `php artisan route:clear` |
| 500 Error | Check `storage/logs/laravel.log` |
| CORS errors | Update `config/cors.php` with frontend URL |
| Token not working | Check Authorization header format: `Bearer TOKEN` |
| Database errors | Verify `.env` database credentials |

---

## 📞 Support Files

All documentation is in your project root:

- `API_DOCUMENTATION.md` - Complete API reference
- `FRONTEND_INTEGRATION_GUIDE.md` - Frontend integration guide
- `DEPLOYMENT_GUIDE.md` - cPanel deployment guide
- `PROJECT_STATUS.md` - Project setup summary
- `SETUP_COMPLETE.md` - Database & setup details

---

## 🎯 Next Steps

1. **Delete installer & test files** (security)
2. **Change default passwords** (security)
3. **Test all endpoints** with Postman
4. **Share API URL with frontend team**
5. **Start building additional features**

---

## ✨ Success!

Your Lead Management CRM backend is now:
- ✅ Deployed on cPanel
- ✅ Running with HTTPS
- ✅ Database configured
- ✅ Sample data loaded
- ✅ API authentication working
- ✅ Ready for frontend integration

**API URL:** `https://leadcrm.vpsappserverone.in/api`

---

**Need help?** Check the documentation files or contact your backend developer.

**Happy coding! 🚀**

