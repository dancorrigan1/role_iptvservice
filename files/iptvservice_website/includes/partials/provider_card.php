<?php
$name = htmlspecialchars($provider['name'] ?? '');
$urlv = htmlspecialchars($provider['url'] ?? '');
?>
<div class="card provider-fieldset" data-index="<?php echo $pIndex; ?>" data-name="<?php echo $name; ?>" data-url="<?php echo $urlv; ?>">
  <div class="card-header">
    <div class="card-title">
      <svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 10l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>Provider <span class="provider-number"><?php echo ($pIndex + 1); ?></span> • <?php echo $name; ?></span>
      <span class="card-sub">Users <?php echo $totalUsers; ?> • Creds <?php echo $totalCreds; ?></span>
    </div>
    <div class="inline">
      <button type="button" class="btn remove-provider-btn">Remove</button>
      <button type="button" class="btn btn-ghost toggle-btn">Toggle</button>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-6">
        <label><span>Name</span>
          <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][name]" value="<?php echo $name; ?>">
        </label>
      </div>
      <div class="col-6">
        <label><span>URL</span>
          <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][url]" value="<?php echo $urlv; ?>">
        </label>
      </div>
    </div>

    <div class="stack">
      <div class="inline">
        <strong>Provider Credentials</strong>
        <span class="count-pill cred-count">0</span>
        <button type="button" class="btn add-credential-btn">Add Credential</button>
      </div>
      <div class="credentials-container">
        <?php foreach ($pc as $pcIndex => $cred): ?>
          <div class="t-row credential-row">
            <div class="col span-4">
              <label><span>Account</span>
                <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][provider_credentials][<?php echo $pcIndex; ?>][account]" value="<?php echo htmlspecialchars($cred['account'] ?? ''); ?>">
              </label>
            </div>
            <div class="col span-3">
              <label><span>Username</span>
                <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][provider_credentials][<?php echo $pcIndex; ?>][username]" value="<?php echo htmlspecialchars($cred['username'] ?? ''); ?>">
              </label>
            </div>
            <div class="col span-3">
              <label><span>Password</span>
                <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][provider_credentials][<?php echo $pcIndex; ?>][password]" value="<?php echo htmlspecialchars($cred['password'] ?? ''); ?>">
              </label>
            </div>
            <div class="col span-2 end">
              <button type="button" class="btn remove-credential-btn">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="stack">
      <div class="inline">
        <strong>Proxy Users</strong>
        <span class="count-pill user-count">0</span>
        <button type="button" class="btn add-user-btn">Add Proxy User</button>
      </div>
      <div class="users-container">
        <?php foreach ($pu as $uIndex => $user): ?>
          <?php $live = boolish($user['live'] ?? true, true); $vod = boolish($user['vod'] ?? false, false); ?>
          <div class="t-row user-row" data-live="<?php echo $live ? 'true' : 'false'; ?>">
            <div class="col span-3">
              <label><span>User Name</span>
                <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][proxy_users][<?php echo $uIndex; ?>][name]" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
              </label>
            </div>
            <div class="col span-3">
              <label><span>Username</span>
                <input class="input username-field" type="text" name="providers[<?php echo $pIndex; ?>][proxy_users][<?php echo $uIndex; ?>][username]" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
              </label>
            </div>
            <div class="col span-3">
              <label><span>Password</span>
                <input class="input" type="text" name="providers[<?php echo $pIndex; ?>][proxy_users][<?php echo $uIndex; ?>][password]" value="<?php echo htmlspecialchars($user['password'] ?? ''); ?>">
              </label>
            </div>
            <div class="col span-1 live-cell">
              <label><span>Live</span>
                <select class="input" name="providers[<?php echo $pIndex; ?>][proxy_users][<?php echo $uIndex; ?>][live]">
                  <option value="true"  <?php echo $live ? 'selected' : ''; ?>>true</option>
                  <option value="false" <?php echo !$live ? 'selected' : ''; ?>>false</option>
                </select>
              </label>
            </div>
            <div class="col span-1">
              <label><span>VOD</span>
                <select class="input" name="providers[<?php echo $pIndex; ?>][proxy_users][<?php echo $uIndex; ?>][vod]">
                  <option value="true"  <?php echo $vod ? 'selected' : ''; ?>>true</option>
                  <option value="false" <?php echo !$vod ? 'selected' : ''; ?>>false</option>
                </select>
              </label>
            </div>
            <div class="col span-1 end">
              <button type="button" class="btn remove-user-btn">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
