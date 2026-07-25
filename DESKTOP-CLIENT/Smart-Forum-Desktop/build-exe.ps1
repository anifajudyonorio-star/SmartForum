<#
.SYNOPSIS
    Builds the Smart Discussion desktop client into a self-contained Windows executable.

.DESCRIPTION
    Compiles the project with Maven, gathers the runtime dependencies, and runs jpackage
    to produce dist\Smart Discussion\Smart Discussion.exe together with a bundled Java
    runtime. The result needs no Java installation on the machine it runs on.

.PARAMETER ApiUrl
    Base URL of the SmartForum server the client should talk to.

.PARAMETER JdkHome
    JDK 25 installation directory. Must contain bin\jpackage.exe.

.PARAMETER MavenCmd
    Path to mvn.cmd. Auto-detected from the bundled IntelliJ Maven when omitted.

.EXAMPLE
    .\build-exe.ps1
    .\build-exe.ps1 -ApiUrl "http://127.0.0.1:8000"
#>
[CmdletBinding()]
param(
    [string]$ApiUrl = "http://147.224.178.246/forum",
    [string]$JdkHome = "C:\Program Files\Java\jdk-25",
    [string]$MavenCmd
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

$AppName = "Smart Discussion"
$MainClass = "com.smartforum.Launcher"

if (-not (Test-Path "$JdkHome\bin\jpackage.exe")) {
    throw "jpackage not found under '$JdkHome'. Pass -JdkHome with your JDK 25 path."
}

if (-not $MavenCmd) {
    $candidates = @(
        "$env:LOCALAPPDATA\Programs\IntelliJ IDEA*\plugins\maven\lib\maven3\bin\mvn.cmd",
        "C:\Program Files\JetBrains\IntelliJ IDEA*\plugins\maven\lib\maven3\bin\mvn.cmd"
    )
    $MavenCmd = $candidates |
        ForEach-Object { Resolve-Path $_ -ErrorAction SilentlyContinue } |
        Select-Object -First 1 -ExpandProperty Path
}
if (-not $MavenCmd -or -not (Test-Path $MavenCmd)) {
    throw "Maven not found. Install it or pass -MavenCmd with the path to mvn.cmd."
}

$env:JAVA_HOME = $JdkHome
$env:PATH = "$JdkHome\bin;$env:PATH"

Write-Host "JDK   : $JdkHome"
Write-Host "Maven : $MavenCmd"
Write-Host "API   : $ApiUrl"

Write-Host "`n[1/4] Compiling and packaging the JAR..."
& $MavenCmd -B package "-Dmaven.test.skip=true"
if ($LASTEXITCODE -ne 0) { throw "Maven build failed." }

Write-Host "`n[2/4] Collecting runtime dependencies..."
& $MavenCmd -B dependency:copy-dependencies "-DoutputDirectory=target/libs" "-DincludeScope=runtime"
if ($LASTEXITCODE -ne 0) { throw "Dependency copy failed." }

$appJar = Get-ChildItem "target\*.jar" | Where-Object { $_.Name -notlike "*-sources.jar" } | Select-Object -First 1
if (-not $appJar) { throw "No application JAR found in target\." }
Copy-Item $appJar.FullName "target\libs\" -Force

Write-Host "`n[3/4] Removing previous build..."
if (Test-Path dist) { Remove-Item dist -Recurse -Force }

Write-Host "`n[4/4] Building the executable with jpackage..."
& "$JdkHome\bin\jpackage.exe" `
    --type app-image `
    --name $AppName `
    --app-version 1.0.0 `
    --vendor "SmartForum" `
    --input target\libs `
    --main-jar $appJar.Name `
    --main-class $MainClass `
    --dest dist `
    --java-options "-Dsf.api.url=$ApiUrl" `
    --java-options "--enable-native-access=ALL-UNNAMED"
if ($LASTEXITCODE -ne 0) { throw "jpackage failed." }

$exe = "dist\$AppName\$AppName.exe"
if (-not (Test-Path $exe)) { throw "Expected executable was not produced at $exe" }

$size = "{0:N0}" -f ((Get-ChildItem dist -Recurse | Measure-Object Length -Sum).Sum / 1MB)
Write-Host "`nBuilt $exe ($size MB including the bundled Java runtime)." -ForegroundColor Green
Write-Host "Distribute the whole 'dist\$AppName' folder, not just the .exe."
