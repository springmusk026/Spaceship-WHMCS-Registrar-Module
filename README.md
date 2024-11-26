# Spaceship Registrar Module for WHMCS


This module integrates the Spaceship API into WHMCS, enabling seamless domain registration, transfer, and renewal functionalities. It is designed to simplify domain management for hosting providers and resellers.

---

## ⚠️ Disclaimer

This module is a **test project** created for personal and educational purposes only. It is not officially associated with or endorsed by the Spaceship company.  

### Important Notes:
- **Bugs and Errors**: The module may contain bugs, errors, or security vulnerabilities.  
- **Use at Your Own Risk**: If you choose to use this module, you do so at your own responsibility.  
- **No Warranty**: The author does not provide any guarantees or support for this module.  

By using this module, you acknowledge and accept these terms. Ensure proper testing and security reviews before deploying it in any production environment.

---
## Features

- **Domain Registration**: Register new domains directly from WHMCS.
- **Domain Transfer**: Handle domain transfers easily.
- **Domain Renewal**: Automate domain renewal processes.
- **Customizable Templates**: Modify domain and error templates as needed.
- **Error Handling**: Built-in error logging for troubleshooting.

---

## Installation

Follow these steps to install the module:

1. Extract the module files.
2. Upload the `spaceship` folder to the `modules/registrars/` directory in your WHMCS installation.
3. Navigate to the WHMCS Admin Panel:
   - Go to **Setup > Products/Services > Domain Registrars**.
   - Activate the "Spaceship" module.
4. Configure your API credentials in the module settings.

---

## Configuration

1. **API Setup**:
   - Open the `config.json` file.
   - Enter your Spaceship API `key` and `secret`.

2. **Language File**:
   - Language strings can be customized in `lang/english.php`.

3. **Custom Templates**:
   - Update `templates/domain.tpl` and `templates/error.tpl` to align with your branding and UX needs.

---

## Logs

- API requests and responses are logged for debugging.
- Logs are stored in `logs/api.log`.

**Example Log Entry**:
```
[2024-11-26 10:30:00] API Request: /domain/register
[2024-11-26 10:30:01] API Response: {"status":"success"}
```

---

## File Structure

```
spaceship/
├── lang/
│   └── english.php       # Language strings for the module
├── lib/
│   ├── SpaceshipAPI.php  # Handles API communication
│   ├── Utils.php         # Utility functions
├── logs/
│   └── api.log           # Log file for API calls
├── templates/
│   ├── domain.tpl        # Template for domain-related tasks
│   └── error.tpl         # Error template
├── config.json           # Configuration file for API keys
├── logo.png              # Logo for the module
├── spaceship.php         # Main PHP file for the module
└── README.md             # Documentation
```

---

## Development Details

- **Main Script**: `spaceship.php` orchestrates the module's functionality.
- **API Handler**: `lib/SpaceshipAPI.php` manages API requests and responses.
- **Utility Functions**: `lib/Utils.php` provides reusable helpers for the module.
- **Templates**: Located in the `templates/` directory, these provide customizable UI elements.

---

## Requirements

- WHMCS 8.x or higher.
- PHP 7.4 or higher.
- Valid Spaceship API credentials.

---

## Troubleshooting

1. **API Errors**:
   - Check the `logs/api.log` file for details.
   - Ensure API credentials in `config.json` are correct.

2. **Template Issues**:
   - Verify the syntax in `domain.tpl` or `error.tpl`.
   - Clear WHMCS template cache if changes are not reflected.

3. **Language Support**:
   - For additional languages, create a new file in `lang/` and update the module settings.

---

## Contributing

Feel free to contribute to this module! Fork the repository, make your changes, and submit a pull request.

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Contact

For support or inquiries:
- **Email**: springmusk@gmail.com
- **Website**: [Basanta Sapkota](https://basantasapkota026.com.np)

---