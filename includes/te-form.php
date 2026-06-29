<?php
/** @var array $form */
/** @var bool $readonly */
/** @var string|null $formAction */
/** @var int|null $reportId */
$readonly = $readonly ?? false;
$formAction = $formAction ?? null;
$reportId = $reportId ?? null;

$expenseEmpty = [
    'line_date' => '', 'description' => '',
    'air' => '', 'hotel' => '', 'home_office' => '', 'cell' => '',
    'rental_car_fuel' => '', 'taxi' => '', 'parking_tolls' => '', 'mileage' => '',
    'entertainment' => '', 'travel_meals' => '', 'shipping_postage' => '',
    'office_supplies' => '', 'misc' => '',
];
$mileageEmpty = ['line_date' => '', 'from_location' => '', 'to_location' => '', 'business_purpose' => '', 'miles' => ''];
$entertainmentEmpty = ['line_date' => '', 'persons' => '', 'place' => '', 'nature_purpose' => '', 'amount' => ''];
$miscEmpty = ['line_date' => '', 'description' => '', 'nature_purpose' => '', 'amount' => ''];

$mapExpenseLine = static function (array $line): array {
    return [
        'line_date'         => (string) ($line['line_date'] ?? $line['LineDate'] ?? ''),
        'description'       => (string) ($line['description'] ?? $line['Description'] ?? ''),
        'air'               => (string) ($line['air'] ?? $line['AmountAir'] ?? ''),
        'hotel'             => (string) ($line['hotel'] ?? $line['AmountHotel'] ?? ''),
        'home_office'       => (string) ($line['home_office'] ?? $line['AmountHomeOffice'] ?? ''),
        'cell'              => (string) ($line['cell'] ?? $line['AmountCell'] ?? ''),
        'rental_car_fuel'   => (string) ($line['rental_car_fuel'] ?? $line['AmountRentalCarFuel'] ?? ''),
        'taxi'              => (string) ($line['taxi'] ?? $line['AmountTaxi'] ?? ''),
        'parking_tolls'     => (string) ($line['parking_tolls'] ?? $line['AmountParkingTolls'] ?? ''),
        'mileage'           => (string) ($line['mileage'] ?? $line['AmountMileage'] ?? ''),
        'entertainment'     => (string) ($line['entertainment'] ?? $line['AmountEntertainment'] ?? ''),
        'travel_meals'      => (string) ($line['travel_meals'] ?? $line['AmountTravelMeals'] ?? ''),
        'shipping_postage'  => (string) ($line['shipping_postage'] ?? $line['AmountShippingPostage'] ?? ''),
        'office_supplies'   => (string) ($line['office_supplies'] ?? $line['AmountOfficeSupplies'] ?? ''),
        'misc'              => (string) ($line['misc'] ?? $line['AmountMisc'] ?? ''),
    ];
};
$mapMileageLine = static function (array $line): array {
    return [
        'line_date'        => (string) ($line['line_date'] ?? $line['LineDate'] ?? ''),
        'from_location'    => (string) ($line['from_location'] ?? $line['FromLocation'] ?? ''),
        'to_location'      => (string) ($line['to_location'] ?? $line['ToLocation'] ?? ''),
        'business_purpose' => (string) ($line['business_purpose'] ?? $line['BusinessPurpose'] ?? ''),
        'miles'            => (string) ($line['miles'] ?? $line['Miles'] ?? ''),
    ];
};
$mapEntertainmentLine = static function (array $line): array {
    return [
        'line_date'      => (string) ($line['line_date'] ?? $line['LineDate'] ?? ''),
        'persons'        => (string) ($line['persons'] ?? $line['PersonsEntertained'] ?? ''),
        'place'          => (string) ($line['place'] ?? $line['Place'] ?? ''),
        'nature_purpose' => (string) ($line['nature_purpose'] ?? $line['NaturePurpose'] ?? ''),
        'amount'         => (string) ($line['amount'] ?? $line['Amount'] ?? ''),
    ];
};
$mapMiscLine = static function (array $line): array {
    return [
        'line_date'      => (string) ($line['line_date'] ?? $line['LineDate'] ?? ''),
        'description'    => (string) ($line['description'] ?? $line['Description'] ?? ''),
        'nature_purpose' => (string) ($line['nature_purpose'] ?? $line['NaturePurpose'] ?? ''),
        'amount'         => (string) ($line['amount'] ?? $line['Amount'] ?? ''),
    ];
};

$padLines = static function (array $lines, int $min, array $empty, callable $mapper): array {
    $mapped = array_map($mapper, $lines);
    while (count($mapped) < $min) {
        $mapped[] = $empty;
    }

    return $mapped;
};

$expenseLines = $padLines($form['expense_lines'] ?? [], 8, $expenseEmpty, $mapExpenseLine);
$mileageLines = $padLines($form['mileage_lines'] ?? [], 5, $mileageEmpty, $mapMileageLine);
$entertainmentLines = $padLines($form['entertainment_lines'] ?? [], 5, $entertainmentEmpty, $mapEntertainmentLine);
$miscLines = $padLines($form['misc_lines'] ?? [], 5, $miscEmpty, $mapMiscLine);

$expenseCategories = [
    'air' => 'Air', 'hotel' => 'Hotel', 'home_office' => 'Home Office', 'cell' => 'Cell',
    'rental_car_fuel' => 'Rental Car & Fuel', 'taxi' => 'Taxi', 'parking_tolls' => 'Parking / Tolls',
    'mileage' => 'Mileage', 'entertainment' => 'Entertainment', 'travel_meals' => 'Travel Meals',
    'shipping_postage' => 'Shipping / Postage', 'office_supplies' => 'Office Supplies', 'misc' => 'Misc.',
];

$renderField = static function (string $name, string $value, string $type = 'text', ?string $class = 'form-input te-line-input') use ($readonly): void {
    if ($readonly) {
        if ($type === 'date' && $value !== '') {
            echo htmlspecialchars(te_format_date($value));
        } elseif ($type === 'number' && $value !== '' && $value !== '0') {
            echo htmlspecialchars($value);
        } elseif ($value !== '' && $value !== '0') {
            echo htmlspecialchars($value);
        } else {
            echo '—';
        }

        return;
    }

    $attrs = 'class="' . htmlspecialchars($class ?? 'form-input te-line-input') . '" name="' . htmlspecialchars($name) . '"';
    if ($type === 'date') {
        echo '<input type="date" ' . $attrs . ' value="' . htmlspecialchars($value) . '" />';
    } elseif ($type === 'number') {
        echo '<input type="number" step="0.01" min="0" ' . $attrs . ' value="' . htmlspecialchars($value) . '" />';
    } else {
        echo '<input type="text" ' . $attrs . ' value="' . htmlspecialchars($value) . '" />';
    }
};
?>
<?php if (!$readonly):
$formActions = capture_form_actions(function () use ($reportId) {
    ?>
    <button class="btn-primary" type="submit">Save expense report</button>
    <?php if ($reportId !== null): ?>
    <a class="btn-secondary" href="/travel-expense/view.php?id=<?= (int) $reportId ?>">Cancel</a>
    <?php else: ?>
    <a class="btn-secondary" href="/travel-expense/">Cancel</a>
    <?php endif; ?>
    <?php
});
?>
<form class="admin-form te-report-form" method="post"<?= $formAction !== null ? ' action="' . htmlspecialchars($formAction) . '"' : '' ?>>
<?php render_form_actions($formActions, 'top'); ?>
<?php endif; ?>

  <section class="account-card">
    <h2>Expense report header</h2>
    <div class="form-grid">
      <div class="form-group">
        <label for="period_start">Period start</label>
        <?php if ($readonly): ?>
        <p><?= htmlspecialchars(te_format_date($form['period_start'] ?? '')) ?></p>
        <?php else: ?>
        <input class="form-input" type="date" id="period_start" name="period_start" value="<?= htmlspecialchars((string) ($form['period_start'] ?? '')) ?>" />
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label for="period_end">Period end</label>
        <?php if ($readonly): ?>
        <p><?= htmlspecialchars(te_format_date($form['period_end'] ?? '')) ?></p>
        <?php else: ?>
        <input class="form-input" type="date" id="period_end" name="period_end" value="<?= htmlspecialchars((string) ($form['period_end'] ?? '')) ?>" />
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label for="mileage_rate">Mileage rate ($/mile)</label>
        <?php if ($readonly): ?>
        <p><?= htmlspecialchars(number_format((float) ($form['mileage_rate'] ?? 0.70), 2)) ?></p>
        <?php else: ?>
        <input class="form-input" type="number" step="0.01" min="0" id="mileage_rate" name="mileage_rate" value="<?= htmlspecialchars((string) ($form['mileage_rate'] ?? '0.70')) ?>" />
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Employee</label>
        <p class="form-static"><?= htmlspecialchars((string) ($form['employee_name'] ?? '')) ?></p>
      </div>
      <div class="form-group form-group-full">
        <label for="business_purpose">Business purpose</label>
        <?php if ($readonly): ?>
        <p><?= nl2br(htmlspecialchars((string) ($form['business_purpose'] ?? '—'))) ?></p>
        <?php else: ?>
        <textarea class="form-input" id="business_purpose" name="business_purpose" rows="3"><?= htmlspecialchars((string) ($form['business_purpose'] ?? '')) ?></textarea>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label for="employee_signed_date">Employee signed date</label>
        <?php if ($readonly): ?>
        <p><?= htmlspecialchars(te_format_date($form['employee_signed_date'] ?? '')) ?></p>
        <?php else: ?>
        <input class="form-input" type="date" id="employee_signed_date" name="employee_signed_date" value="<?= htmlspecialchars((string) ($form['employee_signed_date'] ?? '')) ?>" />
        <?php endif; ?>
      </div>
      <div class="form-group form-group-full">
        <?php if ($readonly): ?>
        <p><strong>Certification:</strong> <?= !empty($form['certification_accepted']) ? 'Accepted' : 'Not accepted' ?></p>
        <?php else: ?>
        <label class="checkbox-label">
          <input type="checkbox" name="certification_accepted" value="1" <?= !empty($form['certification_accepted']) ? 'checked' : '' ?> required />
          I certify that all expenditures on this report are for legitimate company business only.
        </label>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="account-card" style="margin-top: 20px;">
    <h2>Expense lines</h2>
    <div class="admin-table-wrap te-expense-table-wrap">
      <table class="admin-table te-expense-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <?php foreach ($expenseCategories as $label): ?>
            <th><?= htmlspecialchars($label) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expenseLines as $index => $line): ?>
          <tr>
            <td><?php $renderField("expense_lines[{$index}][line_date]", $line['line_date'], 'date'); ?></td>
            <td><?php $renderField("expense_lines[{$index}][description]", $line['description']); ?></td>
            <?php foreach (array_keys($expenseCategories) as $key): ?>
            <td><?php $renderField("expense_lines[{$index}][{$key}]", $line[$key], 'number'); ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="account-card" style="margin-top: 20px;">
    <h2>Mileage (itemized)</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
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
          <?php foreach ($mileageLines as $index => $line): ?>
          <tr>
            <td><?php $renderField("mileage_lines[{$index}][line_date]", $line['line_date'], 'date'); ?></td>
            <td><?php $renderField("mileage_lines[{$index}][from_location]", $line['from_location']); ?></td>
            <td><?php $renderField("mileage_lines[{$index}][to_location]", $line['to_location']); ?></td>
            <td><?php $renderField("mileage_lines[{$index}][business_purpose]", $line['business_purpose']); ?></td>
            <td><?php $renderField("mileage_lines[{$index}][miles]", $line['miles'], 'number'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="account-card" style="margin-top: 20px;">
    <h2>Entertainment (itemized)</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Persons entertained</th>
            <th>Place</th>
            <th>Nature / purpose</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entertainmentLines as $index => $line): ?>
          <tr>
            <td><?php $renderField("entertainment_lines[{$index}][line_date]", $line['line_date'], 'date'); ?></td>
            <td><?php $renderField("entertainment_lines[{$index}][persons]", $line['persons']); ?></td>
            <td><?php $renderField("entertainment_lines[{$index}][place]", $line['place']); ?></td>
            <td><?php $renderField("entertainment_lines[{$index}][nature_purpose]", $line['nature_purpose']); ?></td>
            <td><?php $renderField("entertainment_lines[{$index}][amount]", $line['amount'], 'number'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="account-card" style="margin-top: 20px;">
    <h2>Miscellaneous (itemized)</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Nature / purpose</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($miscLines as $index => $line): ?>
          <tr>
            <td><?php $renderField("misc_lines[{$index}][line_date]", $line['line_date'], 'date'); ?></td>
            <td><?php $renderField("misc_lines[{$index}][description]", $line['description']); ?></td>
            <td><?php $renderField("misc_lines[{$index}][nature_purpose]", $line['nature_purpose']); ?></td>
            <td><?php $renderField("misc_lines[{$index}][amount]", $line['amount'], 'number'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php if (!$readonly): ?>
  <?php render_form_actions($formActions, 'bottom'); ?>
</form>
<?php endif; ?>
