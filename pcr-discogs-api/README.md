# WordPress Plugin Build System - PCR Discogs API

This build system automatically increments your plugin version and creates a deployable zip file for your WordPress plugin.

## Setup

1. **Install Node.js** (if you haven't already): [Download from nodejs.org](https://nodejs.org/)

2. **Navigate to your plugin directory** in VS Code terminal:
   ```bash
   cd path/to/your/plugin
   ```

3. **Install dependencies**:
   ```bash
   npm install
   ```

## Usage

### Basic Commands

- **Patch version bump** (1.0.0 → 1.0.1):
  ```bash
  npm run build
  ```

- **Minor version bump** (1.0.0 → 1.1.0):
  ```bash
  npm run build:minor
  ```

- **Major version bump** (1.0.0 → 2.0.0):
  ```bash
  npm run build:major
  ```

### What happens during build:

1. ✅ Reads current version from `pcr-discogs-api.php` plugin header
2. ✅ Increments version number
3. ✅ Updates version in plugin header AND the define constant
4. ✅ Creates `builds/` directory (if needed)
5. ✅ Creates zip file: `pcr-discogs-api-vX.X.X.zip`

### Files included in zip:

- Main plugin file (`pcr-discogs-api.php`)
- All PHP files (`inc/`, `classes/`, etc.)
- Assets (`assets/css/`, `assets/js/`, `assets/images/`)
- Language files (`languages/`)
- Any other plugin files

### Files excluded from zip:

- `node_modules/`
- `builds/` directory
- `package.json`, `package-lock.json`
- `build.js`
- `.git/`, `.gitignore`
- `.vscode/`
- `README.md`

## Plugin Structure

```
pcr-discogs-api/
├── pcr-discogs-api.php          # Main plugin file
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin.js
│   │   └── frontend.js
│   └── images/
├── inc/                         # Include files
├── classes/                     # PHP classes
├── languages/                   # Translation files
├── build.js                     # Build script
├── package.json                 # NPM configuration
└── README.md                    # This file
```

## Deployment

1. Run your build command
2. Navigate to the `builds/` folder
3. Upload the generated zip file to WordPress:
   - **Admin Dashboard** → **Plugins** → **Add New** → **Upload Plugin**
   - Or via FTP to `/wp-content/plugins/`

## Plugin Features

The PCR Discogs API plugin includes:

- 🎵 Admin menu with vinyl record icon
- ⚙️ Settings page for API configuration
- 🔄 Auto-sync functionality preparation
- 🌐 Translation ready
- 📱 Responsive admin interface
- 🔌 WordPress coding standards compliant

## Development

### Version Management

The build system updates two places in your main plugin file:
1. **Plugin Header**: `Version: X.X.X`
2. **PHP Constant**: `define('PCR_DISCOGS_API_VERSION', 'X.X.X');`

### Adding New Files

Simply add files to your plugin directory. The build system will automatically include them unless they're in the exclusion list.

### Custom Exclusions

Edit the `EXCLUDE_FILES` array in `build.js` to exclude additional files:

```javascript
const EXCLUDE_FILES = [
    'node_modules',
    'builds',
    // Add your custom exclusions here
    'tests/',
    '*.log'
];
```

## Troubleshooting

**"Version not found" error:**
- Make sure your `pcr-discogs-api.php` has a proper plugin header with `Version: X.X.X`

**"archiver not found" error:**
- Run `npm install` to install dependencies

**Plugin not showing in WordPress:**
- Make sure the main plugin file has the correct WordPress plugin header
- Check that the zip file extracts to a folder with the plugin name

**Permission errors:**
- Make sure you have write permissions in your plugin directory

## VS Code Integration

You can also run builds directly from VS Code:
1. Open the integrated terminal (`Ctrl + `` ` ``)
2. Run any of the npm commands above

For even faster access, add these to your VS Code tasks.json:
```json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "Build Plugin (Patch)",
            "type": "shell",
            "command": "npm run build",
            "group": "build"
        },
        {
            "label": "Build Plugin (Minor)",
            "type": "shell",
            "command": "npm run build:minor",
            "group": "build"
        }
    ]
}
```

## Next Steps

1. **Test the plugin**: Upload and activate in WordPress
2. **Add functionality**: Implement Discogs API integration
3. **Create assets**: Add CSS/JS files in the `assets/` directory
4. **Add translations**: Create `.pot` file and language files
5. **Write tests**: Add unit tests for your plugin functionality
