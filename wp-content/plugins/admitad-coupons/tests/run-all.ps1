param(
	[Parameter(Mandatory = $true)]
	[string]$SitePath
)

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$testFiles = Get-ChildItem (Join-Path $PSScriptRoot 'php\test-*.php') | Sort-Object Name

foreach ($testFile in $testFiles) {
	Write-Host "RUN $($testFile.Name)"
	$output = & studio wp --path $SitePath eval-file $testFile.FullName --skip-plugins=admitad-coupons,promokodiki-ajax-filter 2>&1
	$output | Write-Host
	if ($LASTEXITCODE -ne 0 -or ($output -match '^Error:')) {
		throw "Admitad test failed: $($testFile.Name)"
	}
}

Write-Host "All Admitad integration tests passed from $pluginRoot."
