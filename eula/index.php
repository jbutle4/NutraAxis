<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal-site.php';

$pageTitle = 'End User License Agreement | NutraAxis Operations';
$pageDescription = 'End User License Agreement for the NutraAxis Operations internal portal.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner legal-document">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="page-hero">
        <div class="section-label">Legal</div>
        <h1>End User License Agreement</h1>
        <p class="page-lead"><?= htmlspecialchars(LEGAL_APP_NAME) ?> · Effective <?= htmlspecialchars(LEGAL_EFFECTIVE_DATE) ?></p>
      </div>

      <div class="legal-prose">
        <p>This End User License Agreement (“Agreement”) is a legal agreement between you and <?= htmlspecialchars(LEGAL_COMPANY_NAME) ?> (“NutraAxis,” “we,” “us,” or “our”) governing your access to and use of the <?= htmlspecialchars(LEGAL_APP_NAME) ?> web application (the “Application”). By accessing or using the Application, you agree to be bound by this Agreement.</p>

        <h2>1. License grant</h2>
        <p>Subject to your compliance with this Agreement and your organization’s assigned role permissions, NutraAxis grants you a limited, non-exclusive, non-transferable, revocable license to access and use the Application solely for authorized internal business purposes related to NutraAxis operations.</p>

        <h2>2. Authorized users</h2>
        <p>The Application is intended for employees, contractors, and other personnel authorized by NutraAxis or its affiliates. You must use credentials issued to you and must not share your login information with any other person.</p>

        <h2>3. Acceptable use</h2>
        <p>You agree not to:</p>
        <ul>
          <li>Access the Application without authorization or attempt to bypass security controls;</li>
          <li>Use the Application for any unlawful purpose or in violation of company policy;</li>
          <li>Reverse engineer, decompile, scrape, or interfere with the Application or connected systems;</li>
          <li>Upload malicious code or content that could harm the Application, its users, or connected services;</li>
          <li>Misuse data obtained through the Application, including confidential business, customer, supplier, or financial information.</li>
        </ul>

        <h2>4. Integrated services</h2>
        <p>The Application may connect to third-party services such as QuickBooks Online, Zendesk, Microsoft 365, and other business systems. Your use of those services remains subject to the terms and policies of the respective providers. NutraAxis is not responsible for third-party service availability, accuracy, or conduct.</p>

        <h2>5. Intellectual property</h2>
        <p>The Application, including its software, design, branding, documentation, and content made available by NutraAxis, is owned by NutraAxis or its licensors and is protected by intellectual property laws. This Agreement does not transfer any ownership rights to you.</p>

        <h2>6. Data and confidentiality</h2>
        <p>Information accessed through the Application may include confidential business data. You must handle such information in accordance with NutraAxis policies and applicable law. Our collection and use of personal information is described in our <a href="<?= htmlspecialchars(legal_privacy_url()) ?>">Privacy Policy</a>.</p>

        <h2>7. Disclaimers</h2>
        <p>The Application is provided on an “as is” and “as available” basis. To the fullest extent permitted by law, NutraAxis disclaims all warranties, whether express or implied, including implied warranties of merchantability, fitness for a particular purpose, and non-infringement. Operational, accounting, inventory, and reporting information displayed in the Application may depend on third-party systems and should be verified before reliance.</p>

        <h2>8. Limitation of liability</h2>
        <p>To the fullest extent permitted by law, NutraAxis will not be liable for any indirect, incidental, special, consequential, or punitive damages, or for any loss of profits, revenue, data, or business opportunity arising out of or related to your use of the Application. NutraAxis’s total liability for any claim arising out of this Agreement will not exceed one hundred U.S. dollars (US $100), except where liability cannot be limited under applicable law.</p>

        <h2>9. Suspension and termination</h2>
        <p>We may suspend or terminate your access to the Application at any time if you violate this Agreement, if your authorization ends, or if required for security, maintenance, or legal reasons. Upon termination, your right to use the Application ends immediately.</p>

        <h2>10. Changes</h2>
        <p>We may update this Agreement from time to time by posting a revised version at this URL. Material changes will be reflected by updating the effective date above. Continued use of the Application after changes become effective constitutes acceptance of the revised Agreement.</p>

        <h2>11. Governing law</h2>
        <p>This Agreement is governed by the laws of <?= htmlspecialchars(LEGAL_GOVERNING_LAW) ?>, without regard to conflict-of-law principles, except where mandatory local law applies.</p>

        <h2>12. Contact</h2>
        <p>Questions about this Agreement may be directed to <a href="mailto:<?= htmlspecialchars(LEGAL_CONTACT_EMAIL) ?>"><?= htmlspecialchars(LEGAL_CONTACT_EMAIL) ?></a>.</p>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
