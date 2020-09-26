# Flysystem Adapter for OneDrive

[![Author](https://img.shields.io/badge/author-as247-orange)](http://as247.vui360.com/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Installation

```bash
composer require as247/flysystem-onedrive:^1.0
```

## Usage
- Follow [Azure Docs](https://docs.microsoft.com/en-us/azure/active-directory/develop/quickstart-register-app) to obtain your `ClientId, ClientSecret & TenantId`
- Follow [OneDrive Docs](https://docs.microsoft.com/en-us/onedrive/developer/rest-api/getting-started/msa-oauth?view=odsp-graph-online) to obtain your `refreshToken`
```php
$token=new \As247\Flysystem\DriveSupport\Support\OneDriveOauth();
$token->setClientId('[Client ID]');
$token->setTenantId('[Tenant ID]');
$token->setClientSecret('[Client secret]');
$token->setRefreshToken('[Refresh token]');

$graph = new \Microsoft\Graph\Graph();
$graph->setAccessToken($token->getAccessToken());

$adapter = new \As247\Flysystem\OneDrive\OneDriveAdapter($graph, '[root path]');

$filesystem = new \League\Flysystem\Filesystem($adapter);

```
