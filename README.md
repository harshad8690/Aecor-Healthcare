# Aecor Backend

## Setup Guide

### Clone the repository
```
git clone git@github.com:harshad8690/aecor_backend.git
cd aecor_backend
```
### Install dependencies
```
composer instal
```
### Setup Environment
```
copy .env.example to .env
```
### Generate APP_KEY
```
php artisan key:generate
```
### Generate APP_KEY for `--env=testing`
1. Generate a key for the testing environment:
```
php artisan key:generate --env=testing
```
2. set the key in phpunit.xml 
```
<server name="APP_KEY" value="base64:GENERATED_KEY_HERE"/>
```
## Database setup
Create databases manually in MySQL
- Main database: **healthcare**
- Testing database: **test_healthcare**

### Run migrations and seeders
```
php artisan migrate --seed
```
### Passport setup
```
php artisan passport:keys
php artisan passport:client --personal --name="Aecor Personal Access Client"
```
### Start development server
```
php artisan serve
```
### Start cron
```
php artisan schedule:run
```
### Run automated tests
```
php artisan test
```
### Postman API Collection
1. Import collection: App.postman_collection.json (located in root directory)
2. Import environment: Env.postman_environment.json (located in root directory)
3. Select the imported environment before running APIs

## Potential Improvements (If given More Time)

### 1. API Enhancements
- Add additional endpoints, such as:
  - **Get appointment list** for healthcare professionals (useful for UI integration).
  - **Search functionality** to find professionals by:
    - Name
    - Specialty
  - **Sorting and filtering** options based on:
    - Consultation fees
    - Availability
    - Gender

### 2. UI Integration
- Integrate the backend APIs with a frontend user interface to enable end-to-end functionality and improve user experience.

### 3. Healthcare Professional Details
- Include detailed information such as:
  - Basic profile (name, gender, specialization)
  - Clinic details (address, contact info)
  - Consultation fees and availability

### 4. Rating & Review System
- Implement a rating and review system for patients to provide feedback on healthcare professionals, enhancing decision-making for future users.

### 5. Fee Estimation Feature
- Provide **approximate charge estimation** during the appointment booking process to improve transparency and manage user expectations.

