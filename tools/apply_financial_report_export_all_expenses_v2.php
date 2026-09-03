<?php
declare(strict_types=1);

echo "FINANCIAL REPORT EXPORT ALL EXPENSES V2\n";
echo "======================================\n\n";
echo "Patch target: FinancialReportController export expense mapping\n";
echo "Mapping:\n";
echo "- Expense -> Invoice\n";
echo "- Invoice -> project_code\n";
echo "- Invoice -> Quote\n";
echo "- Quote -> Items/Product\n";
echo "\nBackup before applying changes.\n";
