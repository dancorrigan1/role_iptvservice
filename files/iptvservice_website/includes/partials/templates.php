<?php
?>
<div id="providerTemplate" class="hidden">
  <div class="card provider-fieldset" data-index="__INDEX__" data-name="" data-url="">
    <div class="card-header">
      <div class="card-title">
        <svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 10l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Provider <span class="provider-number">__NUM__</span> • New</span>
        <span class="card-sub">Users 0 • Creds 0</span>
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
            <input class="input" type="text" name="providers[__INDEX__][name]" value="">
          </label>
        </div>
        <div class="col-6">
          <label><span>URL</span>
            <input class="input" type="text" name="providers[__INDEX__][url]" value="">
          </label>
        </div>
      </div>
      <div class="stack">
        <div class="inline">
          <strong>Provider Credentials</strong>
          <span class="count-pill cred-count">0</span>
          <button type="button" class="btn add-credential-btn">Add Credential</button>
        </div>
        <div class="credentials-container"></div>
      </div>
      <div class="stack">
        <div class="inline">
          <strong>Proxy Users</strong>
          <span class="count-pill user-count">0</span>
          <button type="button" class="btn add-user-btn">Add Proxy User</button>
        </div>
        <div class="users-container"></div>
      </div>
    </div>
  </div>
</div>

<div id="userTemplate" class="hidden">
  <div class="t-row user-row" data-live="true">
    <div class="col span-3">
      <label><span>User Name</span>
        <input class="input" type="text" name="providers[__PINDEX__][proxy_users][__UINDEX__][name]" value="">
      </label>
    </div>
    <div class="col span-3">
      <label><span>Username</span>
        <input class="input username-field" type="text" name="providers[__PINDEX__][proxy_users][__UINDEX__][username]" value="">
      </label>
    </div>
    <div class="col span-3">
      <label><span>Password</span>
        <input class="input" type="text" name="providers[__PINDEX__][proxy_users][__UINDEX__][password]" value="">
      </label>
    </div>
    <div class="col span-1 live-cell">
      <label><span>Live</span>
        <select class="input" name="providers[__PINDEX__][proxy_users][__UINDEX__][live]">
          <option value="true" selected>true</option>
          <option value="false">false</option>
        </select>
      </label>
    </div>
    <div class="col span-1">
      <label><span>VOD</span>
        <select class="input" name="providers[__PINDEX__][proxy_users][__UINDEX__][vod]">
          <option value="true">true</option>
          <option value="false" selected>false</option>
        </select>
      </label>
    </div>
    <div class="col span-1 end">
      <button type="button" class="btn remove-user-btn">Remove</button>
    </div>
  </div>
</div>

<div id="credentialTemplate" class="hidden">
  <div class="t-row credential-row">
    <div class="col span-4">
      <label><span>Account</span>
        <input class="input" type="text" name="providers[__PINDEX__][provider_credentials][__CINDEX__][account]" value="">
      </label>
    </div>
    <div class="col span-3">
      <label><span>Username</span>
        <input class="input" type="text" name="providers[__PINDEX__][provider_credentials][__CINDEX__][username]" value="">
      </label>
    </div>
    <div class="col span-3">
      <label><span>Password</span>
        <input class="input" type="text" name="providers[__PINDEX__][provider_credentials][__CINDEX__][password]" value="">
      </label>
    </div>
    <div class="col span-2 end">
      <button type="button" class="btn remove-credential-btn">Remove</button>
    </div>
  </div>
</div>
