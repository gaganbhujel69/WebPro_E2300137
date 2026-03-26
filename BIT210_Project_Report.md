# BIT210 Web Programming Project Report

## 1. Introduction
### 1.1 Project Background
The rapid growth of the digital economy has increased the demand for continuous learning and skill development. The EduSkill Marketplace System (EMS) is developed to serve as a centralized platform that connects learners with accredited training providers.

### 1.2 Problem Statement
Currently, learners face fragmented systems when searching for and enrolling in short courses, while training providers struggle to market their courses and manage enrolments efficiently. There is also a lack of standardized oversight by governing bodies (Ministry) to ensure the quality of training providers.

### 1.3 Project Objectives
- To develop a centralized web-based platform for learners to discover, enrol, and review short courses.
- To provide training providers with tools to manage courses, track enrolments, and receive payments.
- To enable Ministry Officers to approve or reject training providers to ensure quality control.

### 1.4 Scope of the System
The system covers three main user roles: Learners, Training Providers, and Ministry Officers. The scope encompasses user authentication, provider verification, course listing and management, enrolment, payment processing, review system, and reporting.

## 2. Project Overview
### 2.1 System Name
EduSkill Marketplace System (EMS)

### 2.2 Target Users
- **Learners**: Individuals seeking to acquire new skills through short courses.
- **Training Providers**: Organizations or individuals offering educational content and training programs.
- **Ministry Officers**: Administrators responsible for overseeing the platform and vetting training providers.

### 2.3 System Features Overview
- **Authentication**: Secure login and registration for all user types.
- **Provider Verification**: Workflow for Ministry Officers to approve/reject provider applications.
- **Course Management**: CRUD operations for providers to manage their course offerings.
- **Enrolment & Payment**: Seamless course booking and payment processing for learners.
- **Reviews**: Post-course review and rating system.
- **Dashboards & Analytics**: Role-specific dashboards with statistical reports.

## 3. Use Case & Task Distribution
*(Note: As specific group member names were not provided, placeholders like Member 1, Member 2 are used. Please replace them with actual names).*

### 3.1 Use Case Diagram Explanation
The system consists of three main actors. The Learner interacts with course browsing, enrolment, payment, and review use cases. The Training Provider handles course management, tracks enrolments, and views their financial reports. The Ministry Officer interacts with provider verification use cases and views system-wide analytics.

### 3.2 Group Member Responsibilities
- **Member 1 (Project Manager & Backend Developer)**: Database design, PHP architecture, authentication, and payment processing.
- **Member 2 (Frontend Developer)**: UI/UX design, HTML/CSS/Bootstrap implementation, and responsive layouts.
- **Member 3 (Full Stack Developer)**: Course management modules, provider dashboard, and file upload functionalities.
- **Member 4 (QA & Documenter)**: Review system, Ministry Officer dashboard, report generation, and system testing.

### 3.3 Individual Task Distribution Table
| Task | Assigned To | Status |
| :--- | :--- | :--- |
| Requirement Analysis & DB Design | Member 1 & 2 | Completed |
| UI Prototyping | Member 2 & 4 | Completed |
| Frontend Dev (HTML/CSS/JS) | Member 2 & 3 | Completed |
| Backend Core & Auth (PHP) | Member 1 | Completed |
| Features (Courses, Provider) | Member 3 | Completed |
| Features (Enrollment, Ministry) | Member 1 & 4 | Completed |
| Documentation & Testing | Member 4 | Completed |

## 4. System Design
### 4.1 System Architecture
EMS follows a Client-Server Architecture utilizing the standard LAMP/XAMPP stack. The client side relies on HTML5, CSS3, JavaScript, and Bootstrap rendered in the browser. The server side uses PHP processing to handle business logic, interacting with a MySQL database.

### 4.2 Database Design
#### 4.2.1 Entity Relationship Diagram (ERD)
The core entities revolve around the `users` table, which handles standard authentication and RBAC (Role-Based Access Control). Detailed relations include:
- `users` 1:1 `providers`
- `providers` 1:N `courses`
- `courses` N:1 `course_categories`
- `users (learner)` 1:N `enrolments` N:1 `courses`
- `enrolments` 1:1 `payments`
- `payments` 1:1 `receipts`
- `enrolments` 1:1 `reviews`

#### 4.2.2 Table Structure & Relationships
- **users**: Stores common credentials and `role` (learner, training_provider, ministry_officer).
- **providers**: Extended profile for providers, linked by `user_id`, storing `status` (pending, approved, rejected).
- **courses**: Details about the course (title, duration, fee, dates, status).
- **enrolments**: Links users and courses, managing `payment_status` and `completion_status`.
- **payments & receipts**: Audit tables mapping exactly to one enrolment.
- **reviews**: Rating and feedback, restricted to 1 review per enrolled course.

## 5. System Implementation
### 5.1 Frontend Development
#### 5.1.1 HTML Structure
Semantic HTML5 tags (`<header>`, `<main>`, `<section>`, `<footer>`) are strictly used to improve SEO and accessibility.
#### 5.1.2 CSS Styling
Custom CSS is used for specific branding rules, overrides, and ensuring contrast and mobile visibility, specifically targeting modular components like the mobile navbar.
#### 5.1.3 Bootstrap Components Used
Bootstrap 5 library facilitates rapid and responsive layout control. Grids, Cards (course listings), Modals (confirmation dialogs), Alerts (flash messages), and dynamic Navbars are heavily utilized.
#### 5.1.4 JavaScript Functionality
Vanilla JS is used for client-side form validation, dynamic visibility toggles (e.g., responsive hamburger menu fixes), and asynchronous rating updates.

### 5.2 Backend Development
#### 5.2.1 PHP Implementation
Object-oriented and procedural PHP handles page routing, database connections, and business logic execution. Common functions are abstracted into an `includes/` directory.
#### 5.2.2 Form Handling & Validation
Server-side validation scrutinizes all POST requests using `htmlspecialchars()` to prevent XSS and prepared statements to prevent SQL injection.
#### 5.2.3 Session & Cookies Management
PHP raw `$_SESSION` global is utilized to maintain user state across pages, caching user IDs and roles to control access levels via middleware-like checks.
#### 5.2.4 File Upload Handling
Provider documentation uploads are strictly checked for MIME types, sized limited, and saved with unique hashed names in the `uploads/` directory to prevent malicious execution.

### 5.3 Database Integration
#### 5.3.1 MySQL Database Connection
Connection is established using the `mysqli` or `PDO` extension, encapsulating credentials within a `config/` directory file.
#### 5.3.2 CRUD Operations
- **Create**: Registering users, adding courses, inserting reviews.
- **Read**: Fetching course lists, generating analytics reports.
- **Update**: Modifying course details, changing provider status to 'approved'.
- **Delete**: Withdrawing enrolments or administrators safely removing cancelled courses.

## 6. Main System Functionalities
### 6.1 Training Provider Registration
Providers fill out a specialized registration form capturing organizational data. An entry is created in `users` and a corresponding `pending` entry in `providers`.
### 6.2 Approval/Rejection by Ministry Officer
Ministry Officers log into a dedicated dashboard listing pending providers. They review uploaded documents and can update the provider status, unlocking provider capabilities.
### 6.3 Course Management
Approved providers access their course management panel to create (Draft/Publish), edit, or remove courses. Constraints ensure end dates > start dates, and capacities are managed.
### 6.4 Course Enrolment
Learners browse courses by category. Clicking 'Enrol' inserts an `enrolments` record with a 'pending' payment status. Unique constraints prevent double enrolment.
### 6.5 Payment & Receipt Generation
Learners process payments (simulated). Upon success, a `payments` and a linked `receipts` record are generated, and the enrolment status switches to 'paid'.
### 6.6 Course Review & Rating
Post completion, learners can leave exactly one rating (1-5) and feedback per course, aggregating into the course's public average score.
### 6.7 Reports & Analytics
The system aggregates data seamlessly: Learners see "Total Spent" and "Courses Completed"; Providers view revenue streams; Ministry views system-wide participant metrics.

## 7. Deployment & Configuration Guide
### 7.1 System Requirements
- XAMPP / WAMP server
- PHP 7.4 or higher
- MySQL / MariaDB

### 7.2 Installation Steps
1. Clone the repository into `/opt/lampp/htdocs/` or `C:\xampp\htdocs\`.
2. Rename folder to `booking_site`.
3. Locate `config.php` and update the database credentials if necessary.

### 7.3 Database Setup Instructions
1. Open phpMyAdmin (`http://localhost/phpmyadmin`).
2. Create a new database named `ems_db`.
3. Import the `database/ems_schema.sql` file to scaffold tables and seed data.

### 7.4 Running the Project
1. Start Apache and MySQL services in the XAMPP Control Panel.
2. Navigate to `http://localhost/booking_site` in a web browser.
3. Access the Ministry Officer account using seeded credentials (`officer@ems.gov`).

### 7.5 Deployment Pipeline
Code versioning is managed via GitHub, maintaining a `main` branch for stable releases and feature branches for localized development. Future deployments can be integrated via Webhooks to shared hosting panels like cPanel.

## 8. GitHub Repository
### 8.1 Repository Link
[Insert GitHub Link Here]
### 8.2 Branching Strategy
- `main`: Production-ready code.
- `dev`: Active integration branch.
- Feature branches (e.g., `feat/auth`, `fix/navbar`).
### 8.3 Commit History Explanation
Standardized commit messages were used. `feat:` for new features, `fix:` for bug resolutions, and `style:` for UI tweaks.

## 9. Screenshots of System
> *(Note: Replace placeholders with actual system screenshots prior to submission)*
### 9.1 Homepage
[Insert Homepage Screenshot]
### 9.2 Login & Registration
[Insert Login/Reg Screenshot]
### 9.3 Provider Dashboard
[Insert Provider Dash Screenshot]
### 9.4 Course Management
[Insert Course Management Screenshot]
### 9.5 Enrolment Process
[Insert Enrolment Screenshot]
### 9.6 Payment & Receipt
[Insert Payment Screenshot]
### 9.7 Review System
[Insert Review Screenshot]
### 9.8 Reports Dashboard
[Insert Reports Screenshot]

## 10. Code Explanation
### 10.1 Frontend Code Snippets
```html
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="#">EMS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
</nav>
```
*Typical Bootstrap responsive navigation implementation used across the portal.*

### 10.2 JavaScript Logic Explanation
```javascript
document.querySelectorAll('.rating-star').forEach(star => {
  star.addEventListener('click', function() {
    let value = this.getAttribute('data-value');
    document.getElementById('rating-input').value = value;
  });
});
```
*Logic facilitating the interactive 5-star clicking mechanism for the course review feature.*

### 10.3 PHP Backend Code Explanation
```php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = htmlspecialchars($_POST['email']);
    $role = htmlspecialchars($_POST['role']);
    // Hash password securely
    $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    // Proceed to insert into database using prepared statements...
}
```
*Standard authentication flow ensuring security standards are met before data insertion.*

### 10.4 Database Queries Explanation
```sql
SELECT c.title, AVG(r.rating) as avg_rating 
FROM courses c 
LEFT JOIN reviews r ON c.course_id = r.course_id 
GROUP BY c.course_id;
```
*Relational query aggregating all reviews for specific courses to display dynamic averages on the browsing page.*

## 11. Technologies Used
### 11.1 HTML5
Used for semantic structure and defining page contents.
### 11.2 CSS3
Applied custom styling, flexbox layouts, and branding colors.
### 11.3 Bootstrap
Version 5 for grid systems, component styling, and mobile responsiveness.
### 11.4 JavaScript
DOM manipulation, client-side validation, and UI interactivity.
### 11.5 PHP
Server-side scripting handling business logic and routing.
### 11.6 MySQL
Relational database management system persisting all application state.
### 11.7 GitHub
Version control and collaborative code management.

## 12. Reflection
### 12.1 Challenges Faced
- Integrating comprehensive relational database constraints without causing cascading errors during development.
- Ensuring perfect UI rendering and contrast across various mobile devices.
- Synchronizing form validations natively between PHP and JavaScript.

### 12.2 Solutions Implemented
- Strict utilization of InnoDB with explicit foreign key cascading rules in the SQL schema.
- Extended debugging and CSS media query adjustments for navbar expansion issues.
- Using centralized PHP logic mapping closely with the frontend JS constraint API.

### 12.3 Learning Outcomes
Developing EMS provided deep insights into full-stack architecture, relational data modeling, and handling multi-actor role-based systems. It solidified understanding of securing user inputs and handling sessions efficiently.

### 12.4 Teamwork Experience
Using GitHub drastically improved our parallel workflow, allowing UI tasks and backend logic to be built simultaneously with minimal merge conflicts.

## 13. Conclusion
The EduSkill Marketplace System represents a comprehensive, secure, and user-friendly platform successfully meeting all primary objectives. By streamlining course delivery and ensuring strict quality verification through the Ministry Officer role, it presents a viable technological solution to educational marketplace fragmentation.

## 14. References
1. PHP Documentation Team. (2024). *PHP Manual*. Retrieved from https://www.php.net/manual/en/
2. Bootstrap Team. (2024). *Bootstrap 5 Documentation*. Retrieved from https://getbootstrap.com/docs/5.0/
3. W3Schools. (2024). *SQL Tutorial*. Retrieved from https://www.w3schools.com/sql/

## 15. Appendices
### 15.1 Source Code Samples
Refer to the GitHub repository for full source access.
### 15.2 Additional Screenshots
Provided in the formal submission package.
### 15.3 Cover Sheet
Attached to physical/digital PDF submission.
### 15.4 Marking Scheme
Included as requested by the assignment brief.
