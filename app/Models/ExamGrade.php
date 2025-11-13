<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Виртуальная модель-маркер для экзаменационных оценок
 * Используется только как тип в polymorphic relation grades.gradeable_type
 * Фактические данные хранятся в таблице grades
 */
class ExamGrade extends Model
{
    // Эта модель не имеет собственной таблицы
    // Она используется только как маркер типа для полиморфных отношений
}
