param(
	[Parameter(Mandatory = $true)]
	[string]$SitePath
)

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot

function Assert-AdmitadDisposableTestDatabase {
	# This is deliberately a site-config boundary, not a command-line opt-in:
	# the configured sentinel must name the same database as WordPress and the
	# database name must visibly be dedicated to tests.  A content database is
	# refused before any eval-file command can mutate it.
	$guardPath = (Join-Path $PSScriptRoot 'php\class-test-environment-guard.php').Replace('\', '/')
	$probe = "require_once '$guardPath'; echo Promokodiki_Admitad_Test_Environment_Guard::configured_identity();"
	$previousErrorActionPreference = $ErrorActionPreference
	try {
		$ErrorActionPreference = 'Continue'
		$output = & studio wp --path $SitePath eval $probe 2>&1
		$exitCode = $LASTEXITCODE
	} finally {
		$ErrorActionPreference = $previousErrorActionPreference
	}
	if ($exitCode -ne 0) {
		throw "Unable to verify the configured WordPress database; refusing the Admitad suite."
	}
	$line = ($output | ForEach-Object { $_.ToString().Trim() } | Where-Object { $_ -match '\|' } | Select-Object -Last 1)
	if (-not $line) {
		throw "No verifiable disposable test database sentinel was found; refusing the Admitad suite."
	}
	$parts = $line -split '\|', 2
	$sentinel = $parts[0]
	$database = $parts[1]
	if ($sentinel -ne $database -or $database -notmatch '(?i)(?:^|[_-])test(?:s)?(?:[_-]|$)') {
		throw "Refusing the Admitad suite: PROMOKODIKI_ADMITAD_TEST_DATABASE must equal the WordPress DB_NAME and DB_NAME must be test-dedicated."
	}
}

Assert-AdmitadDisposableTestDatabase
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
