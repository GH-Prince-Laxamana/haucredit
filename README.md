# HAUCredit - Event Compliance & Records Management System

Holy Angel University Compliance & Records Engine for Documentation and Institutional Tracking (HAUCREDIT) is a web-based event compliance, checklist, and records management application designed to assist student councils and organizations of Holy Angel University in complying with the Office of Student Affairs (OSA) Student Activity Manual.

---

## 🚀 Quick Start (Using Docker)

### Prerequisites
- Install **Docker Desktop** from https://www.docker.com/products/docker-desktop

### Run in 2 Steps:

```bash
# 1. Clone the repository
git clone https://github.com/GH-Prince-Laxamana/haucredit.git
cd haucredit

# 2. Start the application
docker-compose up -d
```

### Access the Application
- **URL**: http://localhost/public/
- **Admin Number**: `203`
- **Admin Password**: `203`
- **Test User Number**: `123`
- **Test User Password**: `123`

---

## 📦 Docker Hub

**Image**: [`dockerniprince/haucredit:latest`](https://hub.docker.com/r/dockerniprince/haucredit)

---

## 📂 Project Structure

```
haucredit/
├── /public              # Web-accessible files (HTML, CSS, JS, PHP pages)
│   ├── /admin_pages     # Admin dashboard and management pages
│   ├── /user_pages      # User dashboard and pages
│   ├── /assets          # Images, styles, and includes
│   └── index.php        # Login page
├── /app                 # Application backend logic
│   ├── database.php     # Database configuration and setup
│   ├── query_builder_functions.php
│   ├── error.php
│   ├── /config          # Configuration files
│   └── /script          # JavaScript utilities
├── Dockerfile           # Docker image configuration
├── docker-compose.yml   # Multi-container orchestration
├── .env.example         # Environment variables template
├── sql/init.sql         # Database initialization script
└── README.md            # This file
```

---

## 🐳 Docker Setup

### What Happens When You Run `docker-compose up -d`?

1. **MySQL Container Starts**
   - Creates `haucredit_db` database
   - Creates `dbuser` account
   - Runs all SQL initialization

2. **PHP Container Starts**
   - Runs `app/database.php`
   - Creates all database tables automatically
   - Creates default admin user

3. **Application is Ready**
   - Access at http://localhost/public/
   - Everything is pre-configured!

### Common Docker Commands

```bash
# START the application
docker-compose up -d

# STOP the application (keeps database data)
docker-compose down

# STOP and DELETE everything (database too - use with caution!)
docker-compose down -v

# View logs (live updates)
docker-compose logs -f

# View logs for specific service
docker-compose logs -f php
docker-compose logs -f mysql

# Check running containers
docker-compose ps

# Rebuild image (after code changes)
docker-compose up -d --build

# Access container shell
docker-compose exec php bash
docker-compose exec mysql mysql -u dbuser -p haucredit_db

# Restart a service
docker-compose restart php
docker-compose restart mysql
```

---

## ⚙️ Environment Configuration

Edit `.env` file to customize:

```env
# Database Configuration
DB_SERVER=mysql
DB_USER=dbuser
DB_PASSWORD=dbpassword
DB_ROOT_PASSWORD=rootpassword
DB_NAME=haucredit_db
DB_PORT=3306

# Application Configuration
APP_PORT=80

# PHP Configuration
PHP_MEMORY_LIMIT=256M
MAX_EXECUTION_TIME=300
```

---

## 🔧 Technology Stack

| Component | Version | Details |
|-----------|---------|---------|
| **PHP** | 8.2 | Web framework |
| **Apache** | 2.4 | Web server with mod_rewrite |
| **MySQL** | 8.0 | Database |
| **Frontend** | HTML/CSS/JS | User interface |

---

## 📋 Default Test Credentials

**Admin Account:**
- **Number**: `203`
- **Password**: `203`

**Test User Account:**
- **Number**: `203`
- **Password**: `123`

---

## 🛠️ Troubleshooting

### Port 80 Already in Use
```bash
# Edit .env file
APP_PORT=8080

# Then access at
http://localhost:8080/public/
```

### Database Connection Errors
```bash
# Wait 10-15 seconds for MySQL to initialize
# Check logs
docker-compose logs mysql

# Verify MySQL is running
docker-compose ps
```

### Container Won't Start
```bash
# Check logs for errors
docker-compose logs

# Rebuild from scratch
docker-compose down
docker-compose up -d --build
```

### Can't Access the Application
1. Wait 10-15 seconds for all services to start
2. Run `docker-compose ps` - all should show "healthy" or "Up"
3. Check logs: `docker-compose logs php`

---

## 📖 Features

- ✅ Multi-stage Docker build for optimization
- ✅ MySQL database with automatic initialization
- ✅ Apache with PHP 8.2 support
- ✅ OPcache for production performance
- ✅ Non-root user execution for security
- ✅ Health checks for service monitoring
- ✅ Volume mounting for data persistence
- ✅ URL rewriting for clean routes

---

## 📝 Important Notes

- **Development Status**: Codes are still in the process of development and are particularly subject to evaluation and revisions, all to produce a product that is efficient, reliable, and centered on the minds of the project's developers.

---

## 🔗 Useful Links

- **GitHub Repository**: https://github.com/GH-Prince-Laxamana/haucredit.git
- **Docker Hub Image**: https://hub.docker.com/r/dockerniprince/haucredit
- **Docker Documentation**: https://docs.docker.com/

---

## 📧 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review Docker logs: `docker-compose logs -f`
3. Verify all services are running: `docker-compose ps`

---

## 👨‍💻 Development Team

- **Prince Laxamana**
- **Dannah Mikayla Sanchez**
- **Justine Lee Larioza**
- **Kenaz Brian Yañez**

---

**Last Updated**: March 22, 2026  
**Docker Hub Username**: dockerniprince  
**Maintained by**: Prince Laxamana