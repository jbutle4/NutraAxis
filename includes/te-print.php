<?php
/** @var array $report */
/** @var array $totals */
/** @var array $expenseLines */
/** @var array $mileageLines */
/** @var array $entertainmentLines */
/** @var array $miscLines */
/** @var array $approvalLog */
$approvalLog = $approvalLog ?? [];

$expenseCategories = [
    'AmountAir' => 'Air',
    'AmountHotel' => 'Hotel',
    'AmountHomeOffice' => 'Home Office',
    'AmountCell' => 'Cell',
    'AmountRentalCarFuel' => 'Rental Car & Fuel',
    'AmountTaxi' => 'Taxi',
    'AmountParkingTolls' => 'Parking / Tolls',
    'AmountMileage' => 'Mileage',
    'AmountEntertainment' => 'Entertainment',
    'AmountTravelMeals' => 'Travel Meals',
    'AmountShippingPostage' => 'Shipping / Postage',
    'AmountOfficeSupplies' => 'Office Supplies',
    'AmountMisc' => 'Misc.',
];
?>
<article class="te-print-document po-print-document">
  <header class="te-print-header po-print-header">
    <div class="te-print-brand po-print-brand">
      <p class="te-print-company">NATIONAL FINANCIALS, LLC.</p>
      <p class="po-print-doc-type te-print-doc-type">Travel &amp; Expense Report</p>
    </div>
    <div class="te-print-meta po-print-meta">
      <h1 class="te-print-report-number po-print-po-number"><?= htmlspecialchars((string) $report['ReportNumber']) ?></h1>
      <p><strong>Status:</strong> <?= htmlspecialchars((string) $report['ReportStatus']) ?></p>
      <p><strong>Period:</strong> <?= htmlspecialchars(te_period_label($report)) ?></p>
      <p><strong>Submitted:</strong> <?= htmlspecialchars(te_format_date($report['SubmittedAt'] ?? null)) ?></p>
    </div>
  </header>

  <section class="te-print-parties po-print-parties">
    <div class="te-print-party po-print-party">
      <h2>Employee</h2>
      <p class="po-print-party-name"><?= htmlspecialchars((string) ($report['EmployeeName'] ?? '—')) ?></p>
      <?php if (!empty($report['EmployeeEmail'])): ?>
      <p><strong>Email:</strong> <?= htmlspecialchars((string) $report['EmployeeEmail']) ?></p>
      <?php endif; ?>
      <p><strong>Signed:</strong> <?= htmlspecialchars(te_format_date($report['EmployeeSignedDate'] ?? null)) ?></p>
      <p><strong>Certification:</strong> <?= !empty($report['CertificationAccepted']) ? 'Accepted' : 'Not accepted' ?></p>
    </div>
    <div class="te-print-party po-print-party">
      <h2>Reimbursement</h2>
      <p><strong>Mileage rate:</strong> $<?= htmlspecialchars(number_format((float) ($report['MileageRate'] ?? 0.70), 2)) ?> / mile</p>
      <p><strong>Total miles:</strong> <?= htmlspecialchars(number_format((float) ($totals['mileage_miles'] ?? 0), 1)) ?></p>
      <p><strong>Mileage reimbursement:</strong> <?= htmlspecialchars(te_format_money($totals['mileage_reimbursement'] ?? 0)) ?></p>
      <p><strong>Total due:</strong> <?= htmlspecialchars(te_format_money($totals['total_due'] ?? $report['TotalReimbursementDue'] ?? 0)) ?></p>
      <?php if (!empty($report['ApprovedTotalDue'])): ?>
      <p><strong>Approved total:</strong> <?= htmlspecialchars(te_format_money($report['ApprovedTotalDue'])) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($report['BusinessPurpose'])): ?>
  <section class="te-print-purpose po-print-notes">
    <h2>Business purpose</h2>
    <p><?= nl2br(htmlspecialchars((string) $report['BusinessPurpose'])) ?></p>
  </section>
  <?php endif; ?>

  <section class="te-print-lines po-print-lines">
    <h2>Expense lines</h2>
    <table class="te-print-table po-print-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <?php foreach ($expenseCategories as $label): ?>
          <th><?= htmlspecialchars($label) ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($expenseLines === []): ?>
        <tr><td colspan="16">No expense lines.</td></tr>
        <?php else: ?>
        <?php foreach ($expenseLines as $line): ?>
        <tr>
          <td><?= htmlspecialchars(te_format_date($line['LineDate'] ?? null)) ?></td>
          <td><?= htmlspecialchars((string) ($line['Description'] ?? '—')) ?></td>
          <?php foreach (array_keys($expenseCategories) as $key): ?>
          <td><?= te_parse_amount($line[$key] ?? 0) > 0 ? htmlspecialchars(te_format_money($line[$key])) : '—' ?></td>
          <?php endforeach; ?>
          <td><?= htmlspecialchars(te_format_money($line['LineTotal'] ?? te_expense_line_total($line))) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="te-print-lines po-print-lines">
    <h2>Mileage (itemized)</h2>
    <table class="te-print-table po-print-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>From</th>
          <th>To</th>
          <th>Purpose</th>
          <th>Miles</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($mileageLines === []): ?>
        <tr><td colspan="5">No mileage lines.</td></tr>
        <?php else: ?>
        <?php foreach ($mileageLines as $line): ?>
        <tr>
          <td><?= htmlspecialchars(te_format_date($line['LineDate'] ?? null)) ?></td>
          <td><?= htmlspecialchars((string) ($line['FromLocation'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($line['ToLocation'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($line['BusinessPurpose'] ?? '—')) ?></td>
          <td><?= htmlspecialchars(number_format((float) ($line['Miles'] ?? 0), 1)) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="te-print-lines po-print-lines">
    <h2>Entertainment (itemized)</h2>
    <table class="te-print-table po-print-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Persons</th>
          <th>Place</th>
          <th>Nature / purpose</th>
          <th>Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($entertainmentLines === []): ?>
        <tr><td colspan="5">No entertainment lines.</td></tr>
        <?php else: ?>
        <?php foreach ($entertainmentLines as $line): ?>
        <tr>
          <td><?= htmlspecialchars(te_format_date($line['LineDate'] ?? null)) ?></td>
          <td><?= htmlspecialchars((string) ($line['PersonsEntertained'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($line['Place'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($line['NaturePurpose'] ?? '—')) ?></td>
          <td><?= htmlspecialchars(te_format_money($line['Amount'] ?? 0)) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="te-print-lines po-print-lines">
    <h2>Miscellaneous (itemized)</h2>
    <table class="te-print-table po-print-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th>Nature / purpose</th>
          <th>Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($miscLines === []): ?>
        <tr><td colspan="4">No miscellaneous lines.</td></tr>
        <?php else: ?>
        <?php foreach ($miscLines as $line): ?>
        <tr>
          <td><?= htmlspecialchars(te_format_date($line['LineDate'] ?? null)) ?></td>
          <td><?= htmlspecialchars((string) ($line['Description'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($line['NaturePurpose'] ?? '—')) ?></td>
          <td><?= htmlspecialchars(te_format_money($line['Amount'] ?? 0)) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="te-print-totals po-print-totals">
    <dl class="te-print-totals-list po-print-totals-list">
      <div><dt>Expense subtotal</dt><dd><?= htmlspecialchars(te_format_money($totals['expense_total'] ?? 0)) ?></dd></div>
      <div><dt>Mileage reimbursement</dt><dd><?= htmlspecialchars(te_format_money($totals['mileage_reimbursement'] ?? 0)) ?></dd></div>
      <div><dt>Entertainment subtotal</dt><dd><?= htmlspecialchars(te_format_money($totals['entertainment_total'] ?? 0)) ?></dd></div>
      <div><dt>Miscellaneous subtotal</dt><dd><?= htmlspecialchars(te_format_money($totals['misc_total'] ?? 0)) ?></dd></div>
      <div class="te-print-total-due po-print-total-due"><dt>Total reimbursement due</dt><dd><?= htmlspecialchars(te_format_money($totals['total_due'] ?? $report['TotalReimbursementDue'] ?? 0)) ?></dd></div>
    </dl>
  </section>

  <?php if ($approvalLog !== []): ?>
  <section class="te-print-approval po-print-notes">
    <h2>Approval history</h2>
    <table class="te-print-table po-print-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Approver</th>
          <th>Result</th>
          <th>Comments</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($approvalLog as $entry): ?>
        <tr>
          <td><?= htmlspecialchars(admin_format_datetime($entry['LogDate'])) ?></td>
          <td><?= htmlspecialchars((string) $entry['ApproverName']) ?></td>
          <td><?= htmlspecialchars((string) $entry['ApproverResult']) ?></td>
          <td><?= htmlspecialchars((string) ($entry['ApproverComments'] ?? '—')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <footer class="te-print-footer po-print-footer">
    <p>Generated from NutraAxis Operations · <?= htmlspecialchars(date('M j, Y g:i A')) ?></p>
  </footer>
</article>
