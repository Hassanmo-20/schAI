<?php

namespace App\Enums;

enum TaskType: string
{
    case Assignment = 'assignment';
    case Quiz = 'quiz';
    case Midterm = 'midterm';
    case Exam = 'exam';
    case Project = 'project';
    case Other = 'other';
}
