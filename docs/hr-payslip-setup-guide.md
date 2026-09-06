# HR Expansion Setup Guide

## Admin Setup
1. `Admin > HR > Attendance Settings` me office, policy, selfie, geo, late aur overtime rules verify karo.
2. `Admin > HR > Salary Structures` me earning components banao jaise `HRA`, `Conveyance`, `Other Allowance`.
3. `Admin > HR > Salary Profiles` me har user ko salary structure aur base salary assign karo.
4. `Admin > HR > Deduction Heads` me heads banao jaise `Attendance Deduction`, `Advance`, `Fine`, `Misc`.
5. `Admin > HR > Payslip Setup` me prefix, company name, notes, footer aur signatory save karo.
6. `Admin > HR > Fraud Settings` me selfie required, duplicate photo threshold, face review required aur payroll block rules set karo.

## Monthly Process
1. HR attendance, leaves, regularizations aur overtime approvals complete kare.
2. HR `Fraud Reviews` me pending selfie cases accept/reject kare.
3. Finance `Payslips` page par manual earnings/deductions add kare.
4. Finance `Payroll Attendance` page par preview verify kare.
5. Finance payroll month freeze kare.
6. `Finance Manager > Payslips` se payslips generate kare.
7. User `My Payslips` se apna payslip download kare.
8. HR/Admin reports page se PDF/Excel exports nikaale.

## User Flow
1. User normal punch-in/punch-out kare.
2. Agar selfie suspicious hai to HR review ke liye pending state dikhegi.
3. Month close ke baad user `My Payslips` screen khole.
4. Download karke earnings, deductions aur net pay dekhe.

## Useful Commands
- `php artisan attendance:generate-payslips 2026 4`
- `php artisan attendance:rebuild-payslip-snapshots 2026 4`
- `php artisan attendance:health-check`

## Before Go Live
1. Server par `WKHTMLTOPDF_BINARY` set karo agar default path use nahi karna hai.
2. `php artisan attendance:health-check` run karke tables, routes, storage, PDF binary verify karo.
3. `php artisan schedule:list` se attendance aur payroll related schedules verify karo.
4. Ek real HR user se fraud review queue aur report exports test karo.
5. Ek real Finance user se manual adjustment, freeze, payslip generation, aur download test karo.
6. Ek real employee user se `My Payslips` aur mobile punch-in/selfie flow test karo.
