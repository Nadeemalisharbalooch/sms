<?php

return [
    // 1. User & Role Management
    'users' => ['view', 'create', 'update', 'delete'],
    'roles' => ['view', 'create', 'update', 'delete'],

    // 2. Core Institute Configuration
    'institute' => ['view', 'update'],
    'academic-sessions' => ['view', 'manage', 'activate'], // 'manage' covers create/update/delete

    // 3. Academic Structure
    'classes' => ['view', 'create', 'update', 'delete'],
    'sections' => ['view', 'create', 'update', 'delete'],
    'subjects' => ['view', 'create', 'update', 'delete'],
    // Merged: subject-teachers, section-teachers, room-teachers
    'teacher-assignments' => ['view', 'manage'], 

    // 4. Student Management
    'students' => ['view', 'create', 'update', 'delete', 'import', 'promote'],

    // 5. Attendance
    'attendance' => ['view', 'mark', 'delete', 'reports'], // 'mark' covers both create and update

    // 6. Fee Management
    // Merged: fee-categories, fee-structures, fee-assignments
    'fee-setup' => ['view', 'manage'], 
    'fee-vouchers' => ['view', 'generate', 'update', 'delete'],
    // Merged: ledger, student-vouchers, collect
    'fee-collections' => ['collect', 'reports'], 

    // 7. Timetable
    // Merged: setup, swap, update into 'manage'
    'timetable' => ['view', 'manage', 'generate'], 

    // 8. System & Billing
    'subscription' => ['view', 'manage'], // 'manage' covers upgrade, payment, invoice
    'dashboard' => ['view'],
];