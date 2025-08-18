<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>IPTV Service Credential Editor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="public/style.css">
</head>
<body>
<div class="header">
  <div class="header-inner">
    <div class="h-title">IPTV Service Credential Editor <span class="badge" id="summaryBadge"></span></div>
    <div class="inline">
      <button form="providersForm" type="submit" class="btn btn-primary">Submit Changes</button>
      <button type="button" id="addProviderBtn" class="btn btn-success">Add Provider</button>
      <button type="button" id="refreshRepoBtn" class="btn btn-ghost">Clean & Refresh Repo</button>
      <button type="button" id="expandAll" class="btn btn-ghost">Expand All</button>
      <button type="button" id="collapseAll" class="btn btn-ghost">Collapse All</button>
    </div>
  </div>
</div>
