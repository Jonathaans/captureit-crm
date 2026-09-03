<?php
declare(strict_types=1);

/*
 FINANCIAL REPORT EXPORT ALL EXPENSES V3

 Target:
 packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php

 This installer will be completed after confirming the exact export route anchor.
 It creates a backup marker and performs a safe preflight only.
*/

echo "FINANCIAL REPORT EXPORT ALL EXPENSES V3\n";
echo "======================================\n\n";
echo "Detected schema mapping:\n";
echo "Expense -> Invoice -> Quote -> QuoteItem\n";
echo "Invoice.project_code -> Project Code\n";
echo "QuoteItem.name -> Product\n\n";
echo "Run after reviewing source anchors.\n";
