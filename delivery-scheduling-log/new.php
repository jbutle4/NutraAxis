<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/delivery-appointment.php';

das_require_create();

$activeSlug = 'delivery-scheduling-log';
$pageContainerClass = 'page-inner--wide';
$returnContext = das_return_context_from_query();
$breadcrumb = das_breadcrumb($returnContext);
$error = null;

$porId = (int) ($_GET['por_id'] ?? 0);
$defaults = $porId > 0 ? das_default_from_receipt($porId) : null;

$form = $defaults ?? [
    'po_receipt_id'            => $porId > 0 ? (string) $porId : '',
    'po_id'                    => '',
    'supplier_id'              => '',
    'company_name'             => '',
    'contact_name'             => '',
    'contact_email'            => '',
    'contact_phone'            => '',
    'appointment_datetime'     => '',
    'appointment_address'      => '',
    'appointment_company_name' => '',
    'receiving_company_contact' => '',
    'receiving_company_email'   => '',
    'receiving_company_phone'   => '',
    'appointment_status'       => 'Not Scheduled',
    'appointment_asn_created'  => !empty($_GET['appointment_asn_number']) ? '1' : '0',
    'appointment_asn_number'   => trim((string) ($_GET['appointment_asn_number'] ?? '')),
    'appointment_notes'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnContext = das_return_context_from_query();
    $form = das_from_input($_POST);
    $result = das_save($_POST);

    if ($result['ok']) {
        $query = http_build_query(array_filter([
            'notice'      => 'created',
            'return_to'   => $returnContext['return_to'] ?: null,
            'por_id'      => $returnContext['por_id'] > 0 ? $returnContext['por_id'] : null,
            'jazz_asn_id' => $returnContext['jazz_asn_id'] !== '' ? $returnContext['jazz_asn_id'] : null,
        ]));
        header('Location: /delivery-scheduling-log/edit.php?id=' . (int) $result['appt_id'] . '&' . $query, true, 303);
        exit;
    }

    $error = $result['error'];
}

$poOptions = das_po_options();
$porOptions = das_por_options();
$supplierOptions = das_supplier_options();

$pageTitle = 'New Appointment | Delivery Scheduling Log';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass ?? '') ?>">
      <?php
      render_list_page_header([
          'back_href'  => $breadcrumb['href'],
          'back_label' => $breadcrumb['label'],
          'category'   => 'Delivery scheduling',
          'title'      => 'New appointment',
          'lead'       => 'Create a delivery appointment record for a purchase order receipt.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/delivery-scheduling-log/new.php' . ($returnContext['return_to'] !== '' || $porId > 0 ? '?' . http_build_query(array_filter([
            'return_to'   => $returnContext['return_to'] ?: null,
            'por_id'      => $porId > 0 ? $porId : ($returnContext['por_id'] > 0 ? $returnContext['por_id'] : null),
            'jazz_asn_id' => $returnContext['jazz_asn_id'] !== '' ? $returnContext['jazz_asn_id'] : null,
        ])) : '');
        require dirname(__DIR__) . '/includes/delivery-appointment-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
