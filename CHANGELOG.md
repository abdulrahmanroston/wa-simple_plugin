# Changelog

All notable changes to WA Simple Queue will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-26

### Added
- Initial release of WA Simple Queue plugin
- Message queue system with priority support
- Automatic retry mechanism for failed messages
- WhatsApp API integration
- Admin panel for queue management
- Message status tracking (pending, sent, failed)
- Rate limiting to prevent API overload
- Metadata support for message context
- WooCommerce order invoice integration
- Simple API for developers (`wa_send()` function)
- Bulk message sending support
- Flexible phone number formatting
- Comprehensive logging system

### Features
- **Queue System**: Automatic background processing
- **Priority Levels**: urgent, high, normal, low
- **Retry Logic**: Up to 3 attempts with exponential backoff
- **Status Tracking**: Real-time message status updates
- **Admin Dashboard**: Easy queue monitoring
- **Developer Friendly**: Simple integration API

---

## Version Guidelines

### Versioning Format: MAJOR.MINOR.PATCH

- **MAJOR**: Breaking changes or major new features
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes and minor improvements

### Examples:
- `1.0.0` → `1.0.1`: Bug fix
- `1.0.1` → `1.1.0`: New feature added
- `1.1.0` → `2.0.0`: Breaking changes

---

## Unreleased

### Planned Features
- [ ] WhatsApp media support (images, documents)
- [ ] Template message support
- [ ] Scheduled message sending
- [ ] Message analytics dashboard
- [ ] Multi-device support
- [ ] Webhook support for delivery status

---

[1.0.0]: https://github.com/abdulrahmanroston/wa-simple_plugin/releases/tag/v1.0.0
