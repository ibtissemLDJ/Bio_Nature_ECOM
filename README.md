# Nescare - Natural Skincare E-commerce Platform

Nescare is a web-based e-commerce platform specializing in natural skincare products. The platform provides a user-friendly interface for customers to browse, purchase, and learn about natural skincare products, while offering administrators a robust management system.

## Features

### Customer Features
- User registration and authentication
- Product browsing and searching
- Shopping cart functionality
- Secure checkout process
- Order history and tracking
- Product reviews and ratings
- Wishlist functionality
- Password recovery system

### Admin Features
- Product management (add, edit, delete)
- Category management
- Order management
- User management
- Inventory tracking
- Sales reports and analytics

## Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/Nescare.git
```

2. Set up your local environment:
   - Install XAMPP/WAMP/MAMP
   - Start Apache and MySQL services

3. Database setup:
   - Create a new database named 'nescare'
   - Import the database schema from `database/nescare.sql`

4. Configuration:
   - Navigate to the project directory
   - Update database connection settings in `includes/db_connection.php`
   - Ensure proper permissions for image uploads

5. Access the application:
   - Customer interface: `http://localhost/Nescare/costumer/`
   - Admin interface: `http://localhost/Nescare/admin/`

## Directory Structure

```
Nescare/
├── admin/              # Admin panel files
├── costumer/          # Customer-facing files
├── images/            # Product and user images
│   ├── products/     # Product images
│   └── users/        # User profile images
├── includes/          # Shared PHP files
├── css/              # Stylesheets
├── js/               # JavaScript files
└── database/         # Database schema and backups
```

## Security Features

- Password hashing using PHP's password_hash()
- SQL injection prevention using prepared statements
- XSS protection through input sanitization
- CSRF protection
- Secure session management
- Input validation and sanitization

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, email support@nescare.com or create an issue in the repository.

## Acknowledgments

- Bootstrap for the frontend framework
- Font Awesome for icons
- jQuery for JavaScript functionality
- All contributors who have helped shape this project

## Version History

- 1.0.0
  - Initial release
  - Basic e-commerce functionality
  - Admin management system
  - User authentication
  - Product management
  - Order processing

## Future Enhancements

- Mobile app development
- Advanced analytics dashboard
- Integration with payment gateways
- Social media integration
- Multi-language support
- Advanced search functionality
- Product recommendation system
