# wp-laracode - WordPress Plugin Generator with Laravel Components

**Create isolated, modern WordPress plugins using Laravel's power without conflicts.** wp-laracode provides a robust framework for developing WordPress plugins that leverage Laravel's elegant syntax and powerful features while maintaining complete isolation between plugins. Each plugin gets its own container, dependencies, and CLI tool - ensuring zero collisions in multi-plugin environments.

## ✨ Features

- **🚀 Complete Dependency Isolation**: Each plugin has its own `vendor/` directory and autoloader
- **⚡ Plugin-Specific CLI**: Generated plugins include their own Laravel Zero binary
- **🔥 Livewire 3 Integration**: Build reactive interfaces with WordPress-native REST endpoints
- **🗄️ Eloquent ORM**: Use Laravel's database layer with WordPress's `$wpdb` connection
- **🎨 Blade Templating**: Full Blade support with plugin-specific view compilation
- **🔧 Modern PHP**: Requires PHP 8.1+ with type safety and modern practices
- **🔄 WordPress-First**: Integrates naturally with WordPress hooks, filters, and standards

## 📦 Quick Start

### 1. Install the CLI Tool

```bash
composer global require andexer/wp-laracode
```

### 2. Create a New Plugin

```bash
# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins

# Create a new plugin
wp-laracode new my-awesome-plugin
```

### 3. Start Developing

```bash
# Enter your plugin directory
cd my-awesome-plugin

# Use the plugin's own CLI
./my-awesome-plugin make:livewire ContactForm
./my-awesome-plugin make:model Product
./my-awesome-plugin migrate

# See all available commands
./my-awesome-plugin list
```

### 4. Activate in WordPress

1. The plugin will appear in WordPress Admin → Plugins
2. Click "Activate"
3. Start building!

## 🏗️ Architecture

wp-laracode creates self-contained WordPress plugins with this structure:

```
my-awesome-plugin/
├── my-awesome-plugin.php          # WordPress entry point
├── my-awesome-plugin              # Plugin-specific CLI binary
├── app/                           # Laravel application structure
│   ├── Console/                   # CLI commands
│   ├── Http/Livewire/             # Livewire components
│   ├── Providers/                 # Service providers
│   └── Models/                    # Eloquent models
├── bootstrap/app.php              # Private container instance
├── config/                        # Isolated configuration
├── vendor/                        # Plugin's own dependencies
└── composer.json                  # Namespaced autoloading
```

## 🎯 Why wp-laracode?

Developing WordPress plugins with modern PHP practices can be challenging due to dependency conflicts and WordPress's procedural nature. wp-laracode solves this by providing:

- **Zero Collisions**: Multiple plugins can use different Laravel versions
- **Modern Workflow**: Use Laravel's artisan-like commands
- **Maintainable Code**: Object-oriented, testable architecture
- **Fast Development**: Generate components with CLI commands

## 📖 Documentation

### Basic Usage

```bash
# Create a new plugin with custom namespace
wp-laracode new booking-system --namespace="BookingSystem"

# Create with specific options
wp-laracode new ecommerce \
  --description="WooCommerce enhancements" \
  --author="Your Name" \
  --license="GPL-2.0-or-later"

# Overwrite existing directory
wp-laracode new my-plugin --force
```

### Plugin CLI Commands

Once inside your plugin directory:

```bash
# Make new components
./my-plugin make:livewire Dashboard
./my-plugin make:model Order --migration
./my-plugin make:controller ApiController

# Database operations
./my-plugin migrate
./my-plugin make:migration create_products_table
./my-plugin db:seed

# Development helpers
./my-plugin route:list
./my-plugin storage:link
./my-plugin config:cache
```

### Livewire in WordPress

wp-laracode automatically sets up Livewire with WordPress:

```php
// In your Livewire component
namespace App\Http\Livewire;

use Livewire\Component;

class ContactForm extends Component
{
    public $name;
    public $email;
    
    public function submit()
    {
        // Validation and WordPress integration
        $this->validate([
            'name' => 'required',
            'email' => 'required|email',
        ]);
        
        // Use WordPress functions
        wp_insert_post([
            'post_title' => $this->name,
            'post_content' => $this->email,
            'post_type' => 'contact_submission',
        ]);
        
        session()->flash('message', 'Form submitted successfully!');
    }
    
    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

## 🔧 Requirements

- PHP 8.1 or higher
- Composer 2.0 or higher
- WordPress 5.9 or higher
- MySQL 5.7+ or MariaDB 10.3+

## 🚀 Advanced Features

### Multiple Plugin Support

Run multiple wp-laracode plugins simultaneously without conflicts:

```bash
# Plugin A (uses Laravel 10)
wp-content/plugins/plugin-a/
├── plugin-a
└── vendor/ (Laravel 10)

# Plugin B (uses Laravel 11)
wp-content/plugins/plugin-b/
├── plugin-b
└── vendor/ (Laravel 11)
```

### Custom Service Providers

Extend your plugin with custom service providers:

```bash
./my-plugin make:provider PaymentServiceProvider
```

### Database Integration

Use Eloquent with WordPress tables:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $primaryKey = 'ID';
    
    public function meta()
    {
        return $this->hasMany(PostMeta::class, 'post_id', 'ID');
    }
}
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

wp-laracode is open-source software licensed under the [MIT license](LICENSE).

### For Companies

If your company:
- Has more than 10 employees
- Generates revenue using this software
- Offers commercial products/services based on this code

Please consider:
1. [Obtaining a commercial license](https://example.com/license)
2. [Becoming a sponsor](https://github.com/sponsors/andexer)
3. [Contributing improvements](CONTRIBUTING.md)

This is not a legal requirement, but an ethical request to support ongoing open-source development.

## 🛠️ Support

- 📚 [Documentation](https://github.com/andexer/wp-laracode/wiki)
- 🐛 [Issue Tracker](https://github.com/andexer/wp-laracode/issues)
- 💬 [Discussions](https://github.com/andexer/wp-laracode/discussions)
- 🚀 [Changelog](CHANGELOG.md)

## 🙏 Acknowledgments

- Built on [Laravel Zero](https://laravel-zero.com)
- Uses [Livewire](https://laravel-livewire.com)
- Inspired by modern WordPress development practices

---

**Ready to transform your WordPress development?** Install wp-laracode today and start building better plugins with Laravel's power and WordPress's flexibility!

```bash
composer global require andexer/wp-laracode
wp-laracode new your-plugin-name
```

Happy coding! 🎉

---
*wp-laracode is not affiliated with or endorsed by the WordPress Foundation or Laravel.*
