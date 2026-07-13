<?php
if (!function_exists('data_profile_is_uat') || !data_profile_is_uat()) {
    return;
}
?>
<div class="uat-environment-banner" role="status">
  <strong>UAT environment</strong>
  <span>This page displays test / UAT data — not production.</span>
</div>
