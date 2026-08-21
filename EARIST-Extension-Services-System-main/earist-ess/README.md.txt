# EARIST Extension Service System (EESS)

A comprehensive web-based information management system for tracking, organizing, and recommending extension programs at Eulogio "Amang" Rodriguez Institute of Science and Technology (EARIST).

![EARIST EESS](https://img.shields.io/badge/EARIST-EESS-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)

##  Features

### **Dashboard & Analytics**
- Real-time statistics and KPI tracking
- Visual charts and graphs using Chart.js
- Monthly trends and program performance metrics
- Quick action buttons for common tasks

###  **User Management** (Admin Only)
- Role-based access control (Admin, Authorized User, Public User)
- User registration and profile management
- Activity logging and audit trails
- Department assignment and permissions

###  **Program Management**
- Create, read, update, and delete extension programs
- Program status tracking (Planned, Ongoing, Completed, Cancelled)
- Resource allocation and budget management
- Program feedback and rating system
- Image gallery and document attachments

###  **AI-Powered Recommendations** (Authorized Users)
- Smart program suggestions based on department expertise
- Community needs analysis and matching
- Seasonal and trend-based recommendations
- Success probability scoring

###  **Public Interface**
- Public program catalog with search and filtering
- Program request submission system
- Feedback collection from beneficiaries
- Mobile-responsive design

### **Reports & Analytics**
- Comprehensive reporting system
- Export to CSV functionality
- Custom date range filtering
- Program performance analysis

###  **System Administration**
- System settings and configuration
- Database backup and maintenance tools
- Audit log management
- Notification system

## Installation Requirements

### System Requirements
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.0 or higher
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **Storage**: Minimum 1GB free space
- **Memory**: 512MB RAM (2GB recommended)

### PHP Extensions Required
```
- php-mysql
- php-gd
- php-json
- php-mbstring
- php-curl
- php-zip
- php-xml
```

## Installation Steps

### 1. Download and Extract
```bash
# Clone or download the project files
git clone https://github.com/your-repo/earist-ess.git
# OR extract the ZIP file to your web directory
```

### 2. XAMPP Installation (Recommended for Development)

#### Step 1: Install XAMPP
1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP in `C:\xampp` (Windows) or `/opt/lampp` (Linux)
3. Start Apache and MySQL services

#### Step 2: Project Setup
1. Copy all project files to `C:\xampp\htdocs\earist-ess\`
2. Open browser and go to `http://localhost/phpmyadmin`
3. Create a new database named `earist_ess`

### 3. Database Setup

#### Step 1: Create Database
```sql
CREATE DATABASE earist_ess CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

#### Step 2: Import Database Schema
1. Open phpMyAdmin
2. Select the `earist_ess` database
3. Go to "Import" tab
4. Choose the `database_schema.sql` file
5. Click "Go" to import

### 4. Configuration

#### Step 1: Database Configuration
Edit `config.php` and update database settings:
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Default XAMPP password is empty
define('DB_NAME', 'earist_ess');
```

#### Step 2: File Permissions
Ensure the following directories are writable:
```
uploads/
uploads/profiles/
uploads/documents/
uploads/images/
backups/
```

### 5. Access the System
1. Open browser and go to `http://localhost/earist-ess/`
2. Use the default credentials to log in:

#### Default Login Credentials
| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |
| Authorized User (COE) | coe_head | admin123 |
| Authorized User (CIT) | cit_head | admin123 |
| Public User | public_user | admin123 |

## 🔒 Security Configuration

### 1. Change Default Passwords
**⚠️ IMPORTANT**: Change all default passwords immediately after installation!

### 2. File Upload Security
- Only allowed file types: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX
- Maximum file size: 10MB (configurable in settings)
- Uploaded files are stored outside web root when possible

### 3. Database Security
- Use strong database passwords
- Create a dedicated database user with limited privileges
- Enable SQL strict mode

### 4. Web Server Security
- Enable HTTPS in production
- Configure proper file permissions
- Use the provided `.htaccess` file for Apache security headers

## 📱 Browser Compatibility

### Supported Browsers
- **Chrome**: 90+
- **Firefox**: 88+
- **Safari**: 14+
- **Edge**: 90+
- **Mobile browsers**: iOS Safari 14+, Chrome Mobile 90+

## 🚀 Production Deployment

### 1. Server Requirements
- PHP 8.0+ with required extensions
- MySQL 8.0+ or MariaDB 10.4+
- SSL Certificate (recommended)
- Regular backup system

### 2. Performance Optimization
- Enable PHP OPcache
- Configure MySQL query cache
- Use CDN for static assets
- Enable GZIP compression

### 3. Security Hardening
```apache
# Add to .htaccess
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com;"
```

## 🛠️ Maintenance

### Regular Tasks
1. **Database Backup**: Weekly automated backups
2. **Log Cleanup**: Monthly audit log cleanup
3. **File Cleanup**: Remove old uploaded files
4. **Security Updates**: Keep PHP and MySQL updated
5. **Monitoring**: Check system performance and error logs

### Backup Commands
```bash
# Database backup
mysqldump -u username -p earist_ess > backup_$(date +%Y%m%d).sql

# File backup
tar -czf files_backup_$(date +%Y%m%d).tar.gz uploads/
```

## 🐛 Troubleshooting

### Common Issues

#### 1. Database Connection Error
```
Solution: Check database credentials in config.php
Verify MySQL service is running
```

#### 2. File Upload Issues
```
Solution: Check directory permissions
Verify max_file_size in PHP settings
Check disk space availability
```

#### 3. Session Issues
```
Solution: Check PHP session configuration
Verify session directory permissions
Clear browser cookies
```

#### 4. Email Notifications Not Working
```
Solution: Configure SMTP settings in config.php
Check firewall settings
Verify email credentials
```

### Error Logs
- **PHP Errors**: Check `error_log` in PHP configuration
- **Apache Errors**: Check Apache error logs
- **System Logs**: Check `audit_logs` table in database

## 📞 Support

### Documentation
- **User Manual**: Available in `/docs/user_manual.pdf`
- **API Documentation**: Available in `/docs/api_documentation.md`
- **Database Schema**: Available in `/docs/database_schema.md`

### Contact Information
- **System Administrator**: admin@earist.edu.ph
- **Technical Support**: it-support@earist.edu.ph
- **Institution**: Eulogio "Amang" Rodriguez Institute of Science and Technology

## 📄 License

This project is developed for educational purposes and internal use at EARIST. All rights reserved.

## 🔄 Version History

### Version 1.0.0 (Current)
- Initial release
- Complete user management system
- Program management with CRUD operations
- AI-powered recommendations
- Public interface and feedback system
- Comprehensive reporting
- System administration tools

---

## 🎉 Getting Started Checklist

- [ ] Install XAMPP or configure web server
- [ ] Create database and import schema
- [ ] Configure database settings
- [ ] Set file permissions
- [ ] Access system and change default passwords
- [ ] Configure system settings
- [ ] Create user accounts
- [ ] Test all functionality
- [ ] Set up backup system
- [ ] Configure email notifications

**🎯 Ready to use!** Your EARIST Extension Service System is now ready for use.

For additional help, please refer to the user manual or contact the system administrator.