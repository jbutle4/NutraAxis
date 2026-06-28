<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal-site.php';

$pageTitle = 'Privacy Policy | NutraAxis Operations';
$pageDescription = 'Privacy Policy for the NutraAxis Operations internal portal.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner legal-document">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Legal',
          'title'      => 'Privacy Policy',
          'lead'       => LEGAL_APP_NAME . ' · Effective ' . LEGAL_EFFECTIVE_DATE,
      ]); ?>

      <div class="legal-prose">
        <p>This Privacy Policy describes how <?= htmlspecialchars(LEGAL_COMPANY_NAME) ?> (“NutraAxis,” “we,” “us,” or “our”) collects, uses, and protects information when you use the <?= htmlspecialchars(LEGAL_APP_NAME) ?> web application (the “Application”).</p>

        <h2>1. Scope</h2>
        <p>This Policy applies to authorized users of the Application. The Application is an internal business portal and is not intended for public consumer use.</p>

        <h2>2. Information we collect</h2>
        <p>We may collect the following categories of information:</p>
        <ul>
          <li><strong>Account information:</strong> name, work email address, assigned role, login timestamps, and password reset activity;</li>
          <li><strong>Usage information:</strong> actions taken within the Application, module access, audit logs, and support interactions initiated through the portal;</li>
          <li><strong>Business data:</strong> operational records you view or manage through the Application, such as purchase orders, inventory data, contracts, links, and accounting references;</li>
          <li><strong>Technical information:</strong> browser type, device information, IP address, session identifiers, and server logs needed to operate and secure the service.</li>
        </ul>

        <h2>3. How we use information</h2>
        <p>We use collected information to:</p>
        <ul>
          <li>Authenticate users and enforce role-based access controls;</li>
          <li>Operate, maintain, and improve the Application;</li>
          <li>Display business data from internal databases and authorized integrations;</li>
          <li>Provide support, troubleshoot issues, and maintain audit and security records;</li>
          <li>Comply with legal obligations and enforce our <a href="<?= htmlspecialchars(legal_eula_url()) ?>">End User License Agreement</a>.</li>
        </ul>

        <h2>4. Third-party services</h2>
        <p>The Application may connect to third-party services that process information according to their own privacy policies, including:</p>
        <ul>
          <li><strong>Microsoft Azure:</strong> application hosting and database services;</li>
          <li><strong>Intuit QuickBooks Online:</strong> accounting data accessed through authorized OAuth connections;</li>
          <li><strong>Zendesk:</strong> support ticket creation and management;</li>
          <li><strong>Email providers:</strong> password reset and operational notifications, where enabled.</li>
        </ul>
        <p>When you connect or use an integrated service, relevant data may be exchanged between the Application and that provider as needed to deliver the requested functionality.</p>

        <h2>5. Cookies and sessions</h2>
        <p>The Application uses session cookies and similar technologies to keep you signed in, protect against unauthorized access, and maintain application state. These cookies are generally required for the Application to function.</p>

        <h2>6. Data retention</h2>
        <p>We retain information for as long as needed to operate the Application, meet legal and business requirements, resolve disputes, and enforce agreements. Retention periods may vary by data type and business function.</p>

        <h2>7. Security</h2>
        <p>We use administrative, technical, and organizational safeguards designed to protect information, including access controls, encrypted connections, and hosted infrastructure security provided by our cloud service providers. No method of transmission or storage is completely secure.</p>

        <h2>8. Access and sharing</h2>
        <p>Access to information within the Application is limited by assigned permissions. We do not sell personal information. We may disclose information when required by law, to protect rights and safety, or to service providers that help us operate the Application under appropriate confidentiality obligations.</p>

        <h2>9. Your choices</h2>
        <p>Authorized users may review certain account details through the My Account area of the Application. Access to business modules is managed by NutraAxis administrators. For account or access changes, contact your administrator or the address below.</p>

        <h2>10. Children’s privacy</h2>
        <p>The Application is not directed to children under 16, and we do not knowingly collect personal information from children.</p>

        <h2>11. Changes to this Policy</h2>
        <p>We may update this Privacy Policy from time to time by posting a revised version at this URL and updating the effective date above. Continued use of the Application after changes become effective constitutes acceptance of the revised Policy.</p>

        <h2>12. Contact</h2>
        <p>Privacy questions or requests may be sent to <a href="mailto:<?= htmlspecialchars(LEGAL_CONTACT_EMAIL) ?>"><?= htmlspecialchars(LEGAL_CONTACT_EMAIL) ?></a>.</p>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
