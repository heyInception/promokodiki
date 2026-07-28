param(
	[Parameter(Mandatory = $true)]
	[string]$SitePath
)

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$testFiles = Get-ChildItem (Join-Path $PSScriptRoot 'php\test-*.php') | Sort-Object Name

foreach ($testFile in $testFiles) {
	Write-Host "RUN $($testFile.Name)"
	$previousErrorActionPreference = $ErrorActionPreference
	try {
		# WP-CLI can emit non-fatal PHP logs and warnings on stderr. Keep them
		# visible, but let the explicit exit-code and harness-error checks below
		# decide whether this test failed.
		$ErrorActionPreference = 'Continue'
		$output = & studio wp --path $SitePath eval-file $testFile.FullName --skip-plugins=admitad-coupons,promokodiki-ajax-filter 2>&1
		$exitCode = $LASTEXITCODE
	} finally {
		$ErrorActionPreference = $previousErrorActionPreference
	}
	$output | Write-Host
	if ($exitCode -ne 0 -or (($output -join "`n") -match '(?m)^Error:')) {
		throw "Admitad test failed: $($testFile.Name)"
	}
}

Write-Host "All Admitad integration tests passed from $pluginRoot."
