# Jobs Samana Cloud

## Description
Jobs Samana Cloud is a comprehensive job management platform designed to streamline the recruitment process for Samana Group LLC. It includes features for candidate profile management, interview reviews, skillset evaluations, certifications, and more. The application integrates with Google Calendar for scheduling and WhatsApp for candidate communication.

## Directory Structure
├── 404.php
├── admin/
│ ├── 404.php
│ ├── admin_page_schema.php
│ ├── candidate_list.php
│ ├── candidate_profile.php
│ ├── candidate_review.php
│ ├── candidate_skillsets.php
│ ├── index.php
│ ├── manage_jobs.php
│ ├── skilset_console.php
│ ├── user_administration.php
│ ├── user_certifications.php
│ ├── user_enrollment.php
│ ├── user_responses.php
├── config/
│ ├── config.php
├── google-login/
│ ├── callback.php
│ ├── composer.json
│ ├── composer.lock
│ ├── config.php
│ ├── credentials.json
│ ├── index.php
│ ├── login.php
│ ├── logout.php
│ ├── profile.php
│ ├── vendor/
├── images/
│ ├── 404.png
│ ├── aws.png
│ ├── citrix.png
│ ├── netscaler.png
│ ├── profile.jpg
│ ├── samana-logo.png
│ ├── samana-main.png
│ ├── uploads/
├── index.php
├── job_description.php
├── README.md
├── register_certifications.php
├── styles.css



## Version 1.2.6 - Update Summary

### Changes in `candidate_profile.php`

- **Session Timeout Check**: Implemented a session timeout check to ensure users are logged out after a period of inactivity.
- **Admin Check**: Added a check to ensure only logged-in users with admin privileges can access the page.
- **Candidate Information Display**: 
  - Displayed candidate's name, email, phone number (with WhatsApp integration), location, English level, and creation date.
  - Integrated a WhatsApp icon next to the phone number, which links directly to a WhatsApp message pre-filled with a custom message.
- **Interview Details**: Displayed interview details such as the process, salary expectation, and availability for admin users.
- **Interview Reviews**:
  - Displayed first, second, and additional interview reviews with ratings and approval status.
  - Used stars to visually represent the ratings.
- **Candidate Skillsets**: 
  - Displayed skillsets with ratings from different reviewers.
  - Used stars to visually represent the ratings and included reviewer initials.
- **Certifications**:
  - Displayed candidate certifications.
  - Added a Calendar icon next to the certifications button, which opens Google Calendar.
- **Google Calendar Integration**:
  - Added functionality to create a Google Calendar event with pre-filled details, including candidate CV and profile links.

### Changes in `user_responses.php`

- **Not specified in the task details, but ensure to summarize any changes made here as well.**

### General Improvements

- **Security Enhancements**: Added sensitive files (`google-login/config.php`, `google-login/credentials.json`, `config/config.php`) to `.gitignore` to prevent them from being tracked in Git.
- **Version Tagging**: Tagged the latest commit as `v1.2.6` to mark the new version with all these updates.

## Previous Updates

### Version 1.2.5 - Previous Update Summary
- [Include details of the previous version if available.]

## Installation Instructions

### Prerequisites

1. **Windows Server** with IIS installed.
2. **PHP** installed and configured.
3. **MySQL** installed and running.
4. **Composer** installed.

### Setting Up IIS with PHP and MySQL

1. **Install IIS**:
   - Open the **Server Manager**.
   - Click on **Add Roles and Features**.
   - Select **Web Server (IIS)** and complete the installation.

2. **Install PHP**:
   - Download the latest version of PHP from the [official website](https://windows.php.net/download/).
   - Extract the PHP files to a directory (e.g., `C:\PHP`).
   - Configure the `php.ini` file as needed.

3. **Configure IIS to Use PHP**:
   - Open **IIS Manager**.
   - Select your server in the left panel.
   - Double-click on **Handler Mappings**.
   - Click **Add Module Mapping**.
     - Request path: `*.php`
     - Module: `FastCgiModule`
     - Executable: `C:\PHP\php-cgi.exe` (or the path to your PHP executable)
     - Name: `PHP`
   - Click **OK** and then **Yes** to enable the mapping.

4. **Install MySQL**:
   - Download and install MySQL from the [official website](https://dev.mysql.com/downloads/mysql/).
   - Follow the installation instructions and set up your root password.

5. **Configure PHP to Work with MySQL**:
   - Open the `php.ini` file.
   - Uncomment the following lines:
     ```ini
     extension=mysqli
     ```

6. **Restart IIS**:
   - Open **Command Prompt** as an administrator.
   - Run the following command:
     ```bash
     iisreset
     ```

### Setting Up the Project

1. **Clone the repository**:
   git clone https://github.com/samanacloud/samanajobs.git

2. **Navigate to the project directory**:
   cd samanajobs

3. **Install dependencies**:
   composer install

4. **Set up your environment variables and configuration files**:
   - Copy `config/config.example.php` to `config/config.php` and fill in the necessary details.
   - Copy `google-login/config.example.php` to `google-login/config.php` and fill in the necessary details.
   - Place your Google OAuth credentials in `google-login/credentials.json`.

## Usage

- Access the application via your web server.
- Use the admin panel to manage job postings, candidate profiles, interviews, and certifications.

## Contributors

- [Juan Pablo Otalvaro](mailto:juan.otalvaro@samanagroup.co)
- [Other Contributors]

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
