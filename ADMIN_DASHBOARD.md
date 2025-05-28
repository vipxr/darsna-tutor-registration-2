# Darsna Tutor Registration - Admin Dashboard

This document describes the admin dashboard functionality for managing LatePoint agents through the WordPress admin interface.

## Overview

The admin dashboard provides a comprehensive interface for managing tutor agents registered through the Darsna Tutor Registration plugin. It integrates seamlessly with WordPress admin and provides full CRUD (Create, Read, Update, Delete) operations for agent management.

## Features

### Agent Management
- **View All Agents**: Display all registered agents in a table format
- **Agent Details**: View comprehensive agent information including personal details, services, and pricing
- **Edit Agents**: Modify agent information directly from the admin interface
- **Status Management**: Activate, deactivate, or set agents to pending status
- **Delete Agents**: Permanently remove agents and their associated data

### Bulk Operations
- **Bulk Activate**: Activate multiple agents at once
- **Bulk Deactivate**: Deactivate multiple agents simultaneously
- **Bulk Delete**: Remove multiple agents in a single operation
- **Select All**: Convenient checkbox to select all agents

### Search and Filtering
- **Search Agents**: Find agents by name, email, or other criteria
- **Status Filtering**: Filter agents by their current status (active, inactive, pending)
- **Service Filtering**: Filter agents by assigned services

### Statistics Dashboard
- **Agent Count**: Total number of registered agents
- **Status Breakdown**: Count of agents by status
- **Recent Registrations**: Latest agent registrations
- **Service Statistics**: Most popular services

## File Structure

```
darsna-tutor-registration/
├── includes/
│   ├── class-darsna-tutor-admin.php    # Main admin class
│   ├── class-darsna-tutor-main.php     # Updated to include admin
│   ├── class-darsna-tutor-backend.php  # Backend functionality
│   └── class-darsna-tutor-frontend.php # Frontend functionality
├── assets/
│   ├── css/
│   │   └── admin.css                   # Admin dashboard styles
│   └── js/
│       └── admin.js                    # Admin dashboard JavaScript
└── darsna-tutor-registration.php       # Main plugin file
```

## Admin Class (`class-darsna-tutor-admin.php`)

### Key Methods

#### `add_admin_menu()`
Adds the "Tutor Agents" menu item to the WordPress admin sidebar.

#### `admin_page()`
Renders the main admin dashboard page with agent listing and management interface.

#### `enqueue_admin_assets()`
Loads the necessary CSS and JavaScript files for the admin interface.

#### `handle_ajax_requests()`
Processes AJAX requests for:
- Updating agent status
- Deleting agents
- Getting agent details
- Updating agent information

#### `get_agents()`
Retrieves agents from the database with optional filtering and pagination.

#### `get_agent_statistics()`
Calculates and returns dashboard statistics.

## JavaScript Functionality (`admin.js`)

### Key Features

#### Modal System
- **Agent Details Modal**: Display comprehensive agent information
- **Edit Agent Modal**: In-place editing of agent details
- **Confirmation Dialogs**: Safe deletion and status changes

#### AJAX Operations
- **Status Updates**: Real-time agent status changes
- **Agent Deletion**: Immediate removal with confirmation
- **Bulk Operations**: Efficient handling of multiple agents
- **Form Submissions**: Seamless agent updates

#### User Experience
- **Loading States**: Visual feedback during operations
- **Success/Error Notifications**: Clear operation feedback
- **Responsive Design**: Works on all device sizes
- **Keyboard Navigation**: Accessible interface

## CSS Styling (`admin.css`)

### Design Elements

#### Layout
- **Responsive Grid**: Adapts to different screen sizes
- **Clean Tables**: Easy-to-read agent listings
- **Modal Overlays**: Professional popup interfaces
- **Status Badges**: Color-coded agent status indicators

#### Interactive Elements
- **Hover Effects**: Visual feedback on interactive elements
- **Button States**: Clear active, hover, and disabled states
- **Form Styling**: Consistent WordPress admin styling
- **Loading Animations**: Smooth operation feedback

## Usage Instructions

### Accessing the Dashboard
1. Log in to WordPress admin
2. Navigate to "Tutor Agents" in the admin menu
3. The dashboard will display all registered agents

### Managing Agents

#### Viewing Agent Details
1. Click the "View" button next to any agent
2. A modal will open showing comprehensive agent information
3. Review personal details, services, and pricing

#### Editing Agents
1. Click the "Edit" button next to any agent
2. Modify the information in the form
3. Click "Update Agent" to save changes

#### Changing Agent Status
1. Use the "Activate" or "Deactivate" buttons for individual agents
2. Or select multiple agents and use bulk actions
3. Confirm the action when prompted

#### Deleting Agents
1. Click the "Delete" button next to an agent
2. Confirm the deletion (this action cannot be undone)
3. The agent and all associated data will be removed

### Bulk Operations
1. Select agents using the checkboxes
2. Choose an action from the "Bulk Actions" dropdown
3. Click "Apply" to execute the action
4. Confirm when prompted

## Security Features

### Nonce Verification
All AJAX requests include WordPress nonces for security validation.

### Capability Checks
Admin functions require appropriate WordPress capabilities.

### Data Sanitization
All user inputs are properly sanitized before database operations.

### SQL Injection Prevention
Prepared statements are used for all database queries.

## Integration with LatePoint

### API Usage
The admin dashboard leverages the existing backend functions that use LatePoint APIs:
- `assign_agent_services()` - For service management
- `set_agent_schedule()` - For schedule management
- `remove_tutor_agent()` - For agent deletion

### Fallback Support
All operations include fallback methods for compatibility with different LatePoint versions.

## Customization

### Adding Custom Fields
To add custom fields to the agent edit form:
1. Modify the `renderAgentEditForm()` function in `admin.js`
2. Update the form processing in the admin class
3. Add corresponding database fields if needed

### Styling Customization
Customize the appearance by modifying `admin.css`:
- Change colors by updating CSS variables
- Modify layout by adjusting grid and flexbox properties
- Add custom animations or transitions

### Adding New Features
Extend functionality by:
1. Adding new methods to the admin class
2. Creating corresponding JavaScript functions
3. Adding new AJAX endpoints
4. Updating the user interface

## Troubleshooting

### Common Issues

#### Admin Menu Not Appearing
- Check if the admin class is properly included
- Verify user capabilities
- Ensure WordPress admin context

#### AJAX Requests Failing
- Check nonce verification
- Verify AJAX URL configuration
- Review server error logs

#### Styling Issues
- Clear browser cache
- Check CSS file loading
- Verify asset URL configuration

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Future Enhancements

### Planned Features
- **Export Functionality**: Export agent data to CSV/Excel
- **Import Agents**: Bulk import from external sources
- **Advanced Filtering**: More sophisticated search options
- **Agent Analytics**: Detailed performance metrics
- **Email Notifications**: Automated agent communications
- **Role Management**: Different access levels for different users

### Performance Optimizations
- **Pagination**: Handle large numbers of agents efficiently
- **Caching**: Cache frequently accessed data
- **Lazy Loading**: Load agent details on demand
- **Database Indexing**: Optimize query performance

## Support

For technical support or feature requests, please refer to the main plugin documentation or contact the development team.