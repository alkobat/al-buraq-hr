# Quick Start Guide - Evaluation Method Feature

## For Administrators

### What's New?
You can now choose how to calculate final evaluation scores:
1. **Manager only** (default) - Use manager's evaluation
2. **Average** - Average of manager and supervisor evaluations

### How to Use

#### Step 1: Navigate to Settings
1. Log in as admin
2. Click **"إعدادات النظام"** (System Settings) in sidebar

#### Step 2: Find Evaluation Method Section
Scroll down to **"طريقة احتساب التقييمات"** (Evaluation Calculation Method)

#### Step 3: Select Method
- ☐ تقييم مدير الإدارة فقط (Manager Only)
- ☐ متوسط تقييمي المدير والمشرف (Average)

#### Step 4: Save
Click **"حفظ"** (Save) button

### Where to See Results
- View any evaluation → See both scores + final calculated score
- Approve evaluation → See all scores with calculation method
- Method note displays below final score

---

## For Developers

### Installation

1. **Deploy Files**
   ```bash
   # Upload these files to server
   app/core/EvaluationCalculator.php (NEW)
   public/admin/settings.php (UPDATED)
   public/view-evaluation.php (UPDATED)
   public/approve.php (UPDATED)
   ```

2. **Run Migration**
   ```bash
   mysql -u root -p database_name < migrations/add_evaluation_method_setting.sql
   ```

3. **Verify**
   ```sql
   SELECT * FROM system_settings WHERE `key` = 'evaluation_method';
   ```

### Quick Test
```bash
php test_evaluation_calculator.php
```

### Usage in Code
```php
require_once 'app/core/EvaluationCalculator.php';

$calculator = new EvaluationCalculator($pdo);

// Get current method
$method = $calculator->getEvaluationMethod();

// Calculate final score
$final = $calculator->calculateFinalScore(80, 90);
// Returns 80 in manager_only mode
// Returns 85 in average mode

// Get all scores for employee
$scores = $calculator->getEmployeeScores($empId, $cycleId);
// Returns: ['manager_score' => 80, 'supervisor_score' => 90, 'final_score' => 85]
```

### Key Files
- **Core**: `app/core/EvaluationCalculator.php`
- **Settings**: `public/admin/settings.php`
- **Display**: `public/view-evaluation.php`, `public/approve.php`
- **Migration**: `migrations/add_evaluation_method_setting.sql`

### Configuration
Database setting: `system_settings.evaluation_method`
- Values: `'manager_only'` or `'average'`
- Default: `'manager_only'`

---

## Troubleshooting

### Issue: Setting not appearing
**Solution**: Run database migration script

### Issue: Scores not calculating correctly
**Solution**: 
1. Check evaluation_method value in database
2. Run test script: `php test_evaluation_calculator.php`
3. Check both evaluations exist in database

### Issue: CSRF error on save
**Solution**: Clear browser cache and refresh page

### Issue: Arabic text not displaying
**Solution**: Verify UTF-8 charset in database and HTML

---

## Quick Reference

### Calculation Methods

| Method | Formula | Example |
|--------|---------|---------|
| manager_only | Final = Manager | Manager: 80, Supervisor: 90 → Final: **80** |
| average | Final = (Manager + Supervisor) / 2 | Manager: 80, Supervisor: 90 → Final: **85** |

### Edge Cases

| Scenario | manager_only | average |
|----------|--------------|---------|
| Both evaluations exist | Manager score | (Manager + Supervisor) / 2 |
| Manager only | Manager score | Manager score |
| Supervisor only | null | Supervisor score |
| Neither | null | null |

---

## Support

### Documentation
- **Full Guide**: [EVALUATION_METHOD_FEATURE.md](EVALUATION_METHOD_FEATURE.md)
- **User Manual**: [README_EVALUATION_METHOD.md](README_EVALUATION_METHOD.md)
- **Changelog**: [CHANGELOG_EVALUATION_METHOD.md](CHANGELOG_EVALUATION_METHOD.md)

### Testing
- **Test Script**: `test_evaluation_calculator.php`
- **Checklist**: [IMPLEMENTATION_CHECKLIST.md](IMPLEMENTATION_CHECKLIST.md)

---

**Version**: 1.0.0  
**Date**: December 13, 2024  
**Status**: Production Ready ✅
