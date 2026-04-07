param(
    [string]$OutputPath = "C:\xampp\htdocs\sales nexus\storage\presentations\Sales_Nexus_CRM_Apresentacao_Diretoria.pptx"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Drawing

$openXmlDllCandidates = @(
    'C:\Program Files\Microsoft Office\root\Office16\ADDINS\Microsoft Power Query for Excel Integrated\bin\DocumentFormat.OpenXml.dll',
    'C:\Program Files\Microsoft Office\root\vfs\ProgramFilesCommonX64\Microsoft Shared\Filters\Documentformat.OpenXml.dll'
)

$openXmlDll = $openXmlDllCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $openXmlDll) {
    throw 'Nao foi possivel localizar a biblioteca DocumentFormat.OpenXml.dll no ambiente.'
}

function Get-Color {
    param([string]$Hex, [int]$Alpha = 255)
    $base = [System.Drawing.ColorTranslator]::FromHtml($Hex)
    [System.Drawing.Color]::FromArgb($Alpha, $base.R, $base.G, $base.B)
}

function New-Brush {
    param([string]$Hex, [int]$Alpha = 255)
    [System.Drawing.SolidBrush]::new((Get-Color -Hex $Hex -Alpha $Alpha))
}

function New-PenHex {
    param([string]$Hex, [float]$Width = 1, [int]$Alpha = 255)
    $pen = [System.Drawing.Pen]::new((Get-Color -Hex $Hex -Alpha $Alpha), $Width)
    $pen.Alignment = [System.Drawing.Drawing2D.PenAlignment]::Inset
    $pen
}

function New-FontPx {
    param([float]$Size, [string]$Style = 'Regular', [string]$Family = 'Segoe UI')
    [System.Drawing.Font]::new($Family, $Size, [System.Drawing.FontStyle]::$Style, [System.Drawing.GraphicsUnit]::Pixel)
}

function New-RoundPath {
    param([float]$X, [float]$Y, [float]$Width, [float]$Height, [float]$Radius)

    $Radius = [Math]::Min($Radius, [Math]::Min($Width / 2, $Height / 2))
    if ($Radius -le 0) {
        $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
        $path.AddRectangle([System.Drawing.RectangleF]::new($X, $Y, $Width, $Height))
        return $path
    }
    $diameter = $Radius * 2
    $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $path.AddArc($X, $Y, $diameter, $diameter, 180, 90)
    $path.AddArc($X + $Width - $diameter, $Y, $diameter, $diameter, 270, 90)
    $path.AddArc($X + $Width - $diameter, $Y + $Height - $diameter, $diameter, $diameter, 0, 90)
    $path.AddArc($X, $Y + $Height - $diameter, $diameter, $diameter, 90, 90)
    $path.CloseFigure()
    $path
}

function Fill-RoundRect {
    param($Graphics, [string]$Hex, [float]$X, [float]$Y, [float]$Width, [float]$Height, [float]$Radius = 24, [int]$Alpha = 255)
    $path = New-RoundPath -X $X -Y $Y -Width $Width -Height $Height -Radius $Radius
    $brush = New-Brush -Hex $Hex -Alpha $Alpha
    $Graphics.FillPath($brush, $path)
    $brush.Dispose()
    $path.Dispose()
}

function Stroke-RoundRect {
    param($Graphics, [string]$Hex, [float]$X, [float]$Y, [float]$Width, [float]$Height, [float]$Radius = 24, [float]$PenWidth = 1, [int]$Alpha = 255)
    $path = New-RoundPath -X $X -Y $Y -Width $Width -Height $Height -Radius $Radius
    $pen = New-PenHex -Hex $Hex -Width $PenWidth -Alpha $Alpha
    $Graphics.DrawPath($pen, $path)
    $pen.Dispose()
    $path.Dispose()
}

function Draw-Text {
    param(
        $Graphics,
        [string]$Text,
        [string]$Hex,
        [float]$X,
        [float]$Y,
        [float]$Width,
        [float]$Height,
        [float]$Size,
        [string]$Style = 'Regular',
        [string]$Align = 'Near',
        [string]$LineAlign = 'Near',
        [int]$Alpha = 255
    )

    $font = New-FontPx -Size $Size -Style $Style
    $brush = New-Brush -Hex $Hex -Alpha $Alpha
    $format = [System.Drawing.StringFormat]::new()
    $format.Alignment = [System.Drawing.StringAlignment]::$Align
    $format.LineAlignment = [System.Drawing.StringAlignment]::$LineAlign
    $format.Trimming = [System.Drawing.StringTrimming]::EllipsisWord
    $Graphics.DrawString($Text, $font, $brush, [System.Drawing.RectangleF]::new($X, $Y, $Width, $Height), $format)
    $format.Dispose()
    $brush.Dispose()
    $font.Dispose()
}

function Draw-Bullets {
    param($Graphics, [string[]]$Items, [string]$Hex, [float]$X, [float]$Y, [float]$Width, [float]$LineHeight = 46, [float]$Size = 19)
    $offsetY = $Y
    foreach ($item in $Items) {
        Draw-Text -Graphics $Graphics -Text '•' -Hex '#FFCA2C' -X $X -Y $offsetY -Width 24 -Height 30 -Size ($Size + 2) -Style 'Bold'
        Draw-Text -Graphics $Graphics -Text $item -Hex $Hex -X ($X + 24) -Y $offsetY -Width ($Width - 24) -Height $LineHeight -Size $Size
        $offsetY += $LineHeight
    }
}

function Draw-Badge {
    param($Graphics, [string]$Text, [string]$Background, [string]$Foreground, [float]$X, [float]$Y, [float]$Width, [float]$Height = 40)
    Fill-RoundRect -Graphics $Graphics -Hex $Background -X $X -Y $Y -Width $Width -Height $Height -Radius 18
    Draw-Text -Graphics $Graphics -Text $Text -Hex $Foreground -X $X -Y ($Y + 2) -Width $Width -Height ($Height - 4) -Size 18 -Style 'Bold' -Align 'Center' -LineAlign 'Center'
}

function Draw-ShadowCard {
    param($Graphics, [float]$X, [float]$Y, [float]$Width, [float]$Height, [string]$Background, [string]$Border, [float]$Radius = 30)
    Fill-RoundRect -Graphics $Graphics -Hex '#091555' -Alpha 20 -X ($X + 10) -Y ($Y + 12) -Width $Width -Height $Height -Radius $Radius
    Fill-RoundRect -Graphics $Graphics -Hex $Background -X $X -Y $Y -Width $Width -Height $Height -Radius $Radius
    Stroke-RoundRect -Graphics $Graphics -Hex $Border -X $X -Y $Y -Width $Width -Height $Height -Radius $Radius -PenWidth 1
}

function Draw-SystemMock {
    param($Graphics, [float]$X, [float]$Y, [float]$Width, [float]$Height, $LogoImage)

    Draw-ShadowCard -Graphics $Graphics -X $X -Y $Y -Width $Width -Height $Height -Background '#FFFFFF' -Border '#D7E4F3' -Radius 34
    Fill-RoundRect -Graphics $Graphics -Hex '#11161E' -X $X -Y $Y -Width 178 -Height $Height -Radius 34
    Fill-RoundRect -Graphics $Graphics -Hex '#FFFFFF' -X ($X + 170) -Y $Y -Width ($Width - 170) -Height $Height -Radius 34
    Fill-RoundRect -Graphics $Graphics -Hex '#11161E' -X ($X + 144) -Y $Y -Width 34 -Height $Height -Radius 0

    if ($LogoImage) {
        $Graphics.DrawImage($LogoImage, [System.Drawing.RectangleF]::new($X + 22, $Y + 18, 110, 42))
    }

    $navY = $Y + 80
    foreach ($item in @('Dashboard', 'Importar ZIP', 'Fila de auditoria', 'Produtos', 'Hierarquia')) {
        $isActive = $item -eq 'Fila de auditoria'
        $bg = if ($isActive) { '#2B250B' } else { '#1A1F27' }
        $border = if ($isActive) { '#FFCA2C' } else { '#2C3340' }
        Draw-ShadowCard -Graphics $Graphics -X ($X + 16) -Y $navY -Width 126 -Height 42 -Background $bg -Border $border -Radius 16
        if ($isActive) {
            Fill-RoundRect -Graphics $Graphics -Hex '#FFCA2C' -X ($X + 24) -Y ($navY + 8) -Width 8 -Height 26 -Radius 4
        }
        Draw-Text -Graphics $Graphics -Text $item -Hex '#F6F8FC' -X ($X + 38) -Y ($navY + 7) -Width 94 -Height 28 -Size 16 -Style 'Bold'
        $navY += 52
    }

    Draw-Text -Graphics $Graphics -Text 'Fila de auditoria' -Hex '#2B475D' -X ($X + 205) -Y ($Y + 34) -Width 340 -Height 42 -Size 34 -Style 'Bold'
    Draw-Badge -Graphics $Graphics -Text 'Todos' -Background '#FFFFFF' -Foreground '#5C7590' -X ($X + 208) -Y ($Y + 100) -Width 122 -Height 40
    Draw-Badge -Graphics $Graphics -Text 'B2C' -Background '#FFFFFF' -Foreground '#5C7590' -X ($X + 344) -Y ($Y + 100) -Width 104 -Height 40
    Draw-Badge -Graphics $Graphics -Text 'Data' -Background '#FFFFFF' -Foreground '#5C7590' -X ($X + 462) -Y ($Y + 100) -Width 124 -Height 40

    $tableX = $X + 202
    $tableY = $Y + 166
    Draw-ShadowCard -Graphics $Graphics -X $tableX -Y $tableY -Width ($Width - 230) -Height ($Height - 198) -Background '#F9FCFF' -Border '#D7E4F3' -Radius 24
    Draw-Text -Graphics $Graphics -Text 'CLIENTE' -Hex '#6B8098' -X ($tableX + 18) -Y ($tableY + 18) -Width 160 -Height 22 -Size 15 -Style 'Bold'
    Draw-Text -Graphics $Graphics -Text 'STATUS' -Hex '#6B8098' -X ($tableX + 282) -Y ($tableY + 18) -Width 120 -Height 22 -Size 15 -Style 'Bold'
    Draw-Text -Graphics $Graphics -Text 'AÇÃO' -Hex '#6B8098' -X ($tableX + 420) -Y ($tableY + 18) -Width 90 -Height 22 -Size 15 -Style 'Bold'

    $rowData = @(
        @{ Name = 'SP-4328336'; Status = 'Pendente'; StatusBg = '#FFF2CF'; StatusFg = '#A36B00'; Action = 'Pegar'; ActionBg = '#FFCA2C'; ActionFg = '#3B2B00' },
        @{ Name = 'SP-4328328'; Status = 'Auditando'; StatusBg = '#E2EEFF'; StatusFg = '#1C61C8'; Action = 'Continuar'; ActionBg = '#314990'; ActionFg = '#FFFFFF' },
        @{ Name = 'SP-4328307'; Status = 'Finalizada'; StatusBg = '#DDF1E5'; StatusFg = '#1F8154'; Action = 'Ver'; ActionBg = '#34A467'; ActionFg = '#FFFFFF' }
    )

    $rowY = $tableY + 56
    foreach ($row in $rowData) {
        $Graphics.DrawLine((New-PenHex -Hex '#E6EEF6' -Width 1), $tableX + 16, $rowY - 10, $tableX + $Width - 248, $rowY - 10)
        Draw-Text -Graphics $Graphics -Text $row.Name -Hex '#2B475D' -X ($tableX + 18) -Y $rowY -Width 180 -Height 24 -Size 17 -Style 'Bold'
        Draw-Badge -Graphics $Graphics -Text $row.Status -Background $row.StatusBg -Foreground $row.StatusFg -X ($tableX + 260) -Y ($rowY - 2) -Width 110 -Height 34
        Draw-Badge -Graphics $Graphics -Text $row.Action -Background $row.ActionBg -Foreground $row.ActionFg -X ($tableX + 400) -Y ($rowY - 2) -Width 84 -Height 34
        $rowY += 66
    }
}

function New-SlideBitmap {
    param([string]$Background = '#EBF3FB', [string]$Background2 = '#FFFFFF', [switch]$Dark)

    $bitmap = [System.Drawing.Bitmap]::new(1600, 900)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

    $rect = [System.Drawing.Rectangle]::new(0, 0, 1600, 900)
    $angle = if ($Dark) { 35 } else { 0 }
    $gradient = [System.Drawing.Drawing2D.LinearGradientBrush]::new($rect, (Get-Color $Background), (Get-Color $Background2), $angle)
    $graphics.FillRectangle($gradient, $rect)
    $gradient.Dispose()

    if ($Dark) {
        Fill-RoundRect -Graphics $graphics -Hex '#20C5DE' -Alpha 14 -X 1140 -Y -100 -Width 540 -Height 320 -Radius 140
        Fill-RoundRect -Graphics $graphics -Hex '#FFCA2C' -Alpha 12 -X -120 -Y 690 -Width 500 -Height 260 -Radius 140
    } else {
        Fill-RoundRect -Graphics $graphics -Hex '#20C5DE' -Alpha 10 -X 1180 -Y -80 -Width 420 -Height 240 -Radius 120
        Fill-RoundRect -Graphics $graphics -Hex '#FFCA2C' -Alpha 10 -X -140 -Y 730 -Width 460 -Height 220 -Radius 120
    }

    @{
        Bitmap = $bitmap
        Graphics = $graphics
    }
}

function Draw-TopRibbon {
    param($Graphics, [string]$Title)
    Fill-RoundRect -Graphics $Graphics -Hex '#091555' -X 54 -Y 42 -Width 258 -Height 44 -Radius 18
    Draw-Text -Graphics $Graphics -Text 'SALES NEXUS CRM' -Hex '#FFFFFF' -X 76 -Y 52 -Width 220 -Height 24 -Size 18 -Style 'Bold'
    Draw-Text -Graphics $Graphics -Text $Title -Hex '#2B475D' -X 72 -Y 110 -Width 980 -Height 74 -Size 46 -Style 'Bold'
}

function Draw-SlideNumber {
    param($Graphics, [int]$Number, [switch]$Dark)
    $bg = if ($Dark) { '#FFFFFF' } else { '#091555' }
    $fg = if ($Dark) { '#091555' } else { '#FFFFFF' }
    Draw-Badge -Graphics $Graphics -Text ('0' + $Number) -Background $bg -Foreground $fg -X 1450 -Y 820 -Width 84 -Height 42
}

function Save-Slide {
    param($Slide, [string]$Path)
    $Slide.Bitmap.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $Slide.Graphics.Dispose()
    $Slide.Bitmap.Dispose()
}

$presentationDir = Split-Path -Parent $OutputPath
$buildDir = Join-Path $presentationDir '_build_sales_nexus_pitch'
$slidesDir = Join-Path $buildDir 'slides'

if (Test-Path $buildDir) {
    Remove-Item -Path $buildDir -Recurse -Force
}

New-Item -ItemType Directory -Path $slidesDir -Force | Out-Null
New-Item -ItemType Directory -Path $presentationDir -Force | Out-Null

$logoPath = 'C:\xampp\htdocs\sales nexus\assets\nexuspgi_light.png'
$logoImage = if (Test-Path $logoPath) { [System.Drawing.Image]::FromFile($logoPath) } else { $null }

$slideTitles = @(
    'Sales Nexus CRM',
    'Por que o projeto importa',
    'Fluxo operacional de ponta a ponta',
    'Módulos que estruturam a operação',
    'Governança, segurança e compliance',
    'Desempenho operacional para escala',
    'Versionamento comercial e de headcount',
    'Valor executivo para a diretoria',
    'Próximos passos recomendados'
)

$slidePaths = New-Object System.Collections.Generic.List[string]

# Slide 1
$slide = New-SlideBitmap -Background '#091555' -Background2 '#162C84' -Dark
$g = $slide.Graphics
if ($logoImage) {
    $g.DrawImage($logoImage, [System.Drawing.RectangleF]::new(86, 72, 220, 84))
}
Draw-Badge -Graphics $g -Text 'APRESENTAÇÃO EXECUTIVA' -Background '#1D348F' -Foreground '#FFFFFF' -X 1220 -Y 76 -Width 290 -Height 44
Draw-Text -Graphics $g -Text 'Sales Nexus CRM' -Hex '#DDE8FF' -X 86 -Y 180 -Width 520 -Height 40 -Size 24 -Style 'Bold'
Draw-Text -Graphics $g -Text 'Programa de Gestão Interativa para operações de vendas Telecom' -Hex '#FFFFFF' -X 86 -Y 228 -Width 760 -Height 164 -Size 64 -Style 'Bold'
Draw-Text -Graphics $g -Text 'Uma plataforma web para centralizar importação, fila de auditoria, hierarquia comercial, catálogo de produtos e governança da operação.' -Hex '#D5DDF5' -X 90 -Y 406 -Width 680 -Height 110 -Size 28
Draw-SystemMock -Graphics $g -X 810 -Y 165 -Width 660 -Height 470 -LogoImage $logoImage
Draw-ShadowCard -Graphics $g -X 86 -Y 660 -Width 360 -Height 120 -Background '#122170' -Border '#2B3E9E'
Draw-Text -Graphics $g -Text 'Governança e segurança' -Hex '#FFFFFF' -X 114 -Y 688 -Width 290 -Height 28 -Size 24 -Style 'Bold'
Draw-Text -Graphics $g -Text 'Perfis, login por CPF, rastreabilidade e visão por escopo operacional.' -Hex '#D6DFF8' -X 114 -Y 724 -Width 292 -Height 46 -Size 18
Draw-ShadowCard -Graphics $g -X 466 -Y 660 -Width 360 -Height 120 -Background '#122170' -Border '#2B3E9E'
Draw-Text -Graphics $g -Text 'Produtividade real' -Hex '#FFFFFF' -X 494 -Y 688 -Width 290 -Height 28 -Size 24 -Style 'Bold'
Draw-Text -Graphics $g -Text 'Fila em atualização quase em tempo real, auditoria guiada e menos retrabalho.' -Hex '#D6DFF8' -X 494 -Y 724 -Width 294 -Height 46 -Size 18
Draw-ShadowCard -Graphics $g -X 846 -Y 660 -Width 620 -Height 120 -Background '#122170' -Border '#2B3E9E'
Draw-Text -Graphics $g -Text 'Decisão executiva' -Hex '#FFFFFF' -X 876 -Y 688 -Width 220 -Height 28 -Size 24 -Style 'Bold'
Draw-Text -Graphics $g -Text 'Aprovar o Sales Nexus significa transformar auditoria operacional em um processo padronizado, escalável e gerenciável.' -Hex '#D6DFF8' -X 876 -Y 722 -Width 540 -Height 50 -Size 18
Draw-SlideNumber -Graphics $g -Number 1 -Dark
$path = Join-Path $slidesDir 'slide01.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 2
$slide = New-SlideBitmap -Background '#EBF3FB' -Background2 '#FFFFFF'
$g = $slide.Graphics
Draw-TopRibbon -Graphics $g -Title 'Por que o projeto importa'
Draw-ShadowCard -Graphics $g -X 76 -Y 208 -Width 670 -Height 520 -Background '#FFFFFF' -Border '#D4E0EC'
Draw-Text -Graphics $g -Text 'Sem um CRM operacional centralizado' -Hex '#2B475D' -X 108 -Y 238 -Width 520 -Height 34 -Size 28 -Style 'Bold'
Draw-Bullets -Graphics $g -Items @(
    'importações manuais geram retrabalho e disputa de ownership da fila',
    'a auditoria depende de conferências repetidas e informação espalhada',
    'alterações de headcount e catálogo podem comprometer o histórico',
    'a visão gerencial fica limitada para cobrança, produtividade e qualidade',
    'a operação perde velocidade quando cresce o volume ou muda a estrutura'
) -Hex '#5F738A' -X 110 -Y 300 -Width 560 -LineHeight 72 -Size 22
Draw-ShadowCard -Graphics $g -X 786 -Y 208 -Width 738 -Height 520 -Background '#0F1B5B' -Border '#213497'
Draw-Text -Graphics $g -Text 'Com o Sales Nexus CRM' -Hex '#FFFFFF' -X 826 -Y 240 -Width 320 -Height 34 -Size 28 -Style 'Bold'
Draw-Bullets -Graphics $g -Items @(
    'a operação passa a trabalhar em um único ambiente web, auditável e versionado',
    'cada venda entra filtrada, deduplicada e distribuída para tratamento padronizado',
    'usuários enxergam apenas o que podem operar: FULL, regional ou escopo personalizado',
    'mudanças de headcount e produtos respeitam período, preservando o histórico',
    'a diretoria ganha base pronta para evolução de indicadores e BI'
) -Hex '#D8E3FF' -X 826 -Y 302 -Width 620 -LineHeight 70 -Size 22
Draw-Badge -Graphics $g -Text '1 ambiente web' -Background '#FFCA2C' -Foreground '#392700' -X 804 -Y 760 -Width 202 -Height 46
Draw-Badge -Graphics $g -Text '3 perfis + escopo' -Background '#1CC5DF' -Foreground '#072639' -X 1028 -Y 760 -Width 232 -Height 46
Draw-Badge -Graphics $g -Text 'Histórico preservado' -Background '#E1F3E8' -Foreground '#146A43' -X 1282 -Y 760 -Width 220 -Height 46
Draw-SlideNumber -Graphics $g -Number 2
$path = Join-Path $slidesDir 'slide02.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 3
$slide = New-SlideBitmap -Background '#0A144E' -Background2 '#142878' -Dark
$g = $slide.Graphics
Draw-Text -Graphics $g -Text 'Fluxo operacional de ponta a ponta' -Hex '#FFFFFF' -X 82 -Y 82 -Width 820 -Height 58 -Size 50 -Style 'Bold'
Draw-Text -Graphics $g -Text 'O CRM foi desenhado para operar o ciclo completo da venda, do arquivo de origem até a base final de gestão.' -Hex '#D6DFF8' -X 86 -Y 150 -Width 820 -Height 54 -Size 24
$stepData = @(
    @{ No = '01'; Title = 'Importar ZIP'; Text = 'Recebe o pacote enviado pela operação, diversas vezes ao dia.' },
    @{ No = '02'; Title = 'Filtrar e deduplicar'; Text = 'Só entra o que for Aprovado, Finalizada e Banda Larga; chave por Código da Venda.' },
    @{ No = '03'; Title = 'Fila em tempo real'; Text = 'Backoffice assume a venda, supervisor destrava quando necessário e todos enxergam o status atualizado.' },
    @{ No = '04'; Title = 'Auditoria por etapas'; Text = 'Resumo, ordem, endereço, hierarquia e produtos com dados pré-preenchidos e validações.' },
    @{ No = '05'; Title = 'Gravação estruturada'; Text = 'Venda finalizada é salva na sales_nexus, pronta para gestão e evolução analítica.' }
)
$startX = 88
$stepWidth = 268
$stepGap = 22
$stepY = 270
for ($i = 0; $i -lt $stepData.Count; $i++) {
    $item = $stepData[$i]
    $x = $startX + (($stepWidth + $stepGap) * $i)
    Draw-ShadowCard -Graphics $g -X $x -Y $stepY -Width $stepWidth -Height 420 -Background '#121E62' -Border '#273A98'
    Draw-Badge -Graphics $g -Text $item.No -Background '#FFCA2C' -Foreground '#392700' -X ($x + 26) -Y ($stepY + 24) -Width 66 -Height 38
    Draw-Text -Graphics $g -Text $item.Title -Hex '#FFFFFF' -X ($x + 26) -Y ($stepY + 82) -Width 210 -Height 62 -Size 28 -Style 'Bold'
    Draw-Text -Graphics $g -Text $item.Text -Hex '#D6DFF8' -X ($x + 26) -Y ($stepY + 162) -Width 214 -Height 210 -Size 20
    if ($i -lt ($stepData.Count - 1)) {
        Fill-RoundRect -Graphics $g -Hex '#1CC5DF' -Alpha 120 -X ($x + $stepWidth + 4) -Y ($stepY + 188) -Width 18 -Height 18 -Radius 9
        $pen = New-PenHex -Hex '#1CC5DF' -Width 5 -Alpha 140
        $g.DrawLine($pen, $x + $stepWidth + 20, $stepY + 197, $x + $stepWidth + $stepGap - 8, $stepY + 197)
        $pen.Dispose()
    }
}
Draw-Badge -Graphics $g -Text 'IMPORTAÇÃO RECORRENTE' -Background '#FFFFFF' -Foreground '#091555' -X 96 -Y 756 -Width 270 -Height 42
Draw-Badge -Graphics $g -Text 'FILA COM OWNERSHIP' -Background '#FFFFFF' -Foreground '#091555' -X 384 -Y 756 -Width 240 -Height 42
Draw-Badge -Graphics $g -Text 'BASE FINAL ESTRUTURADA' -Background '#FFFFFF' -Foreground '#091555' -X 646 -Y 756 -Width 286 -Height 42
Draw-SlideNumber -Graphics $g -Number 3 -Dark
$path = Join-Path $slidesDir 'slide03.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 4
$slide = New-SlideBitmap -Background '#F1F7FD' -Background2 '#FFFFFF'
$g = $slide.Graphics
Draw-TopRibbon -Graphics $g -Title 'Módulos que estruturam a operação'
$moduleCards = @(
    @{ X = 76; Y = 210; W = 690; H = 250; Title = 'Fila de auditoria'; Body = 'Paginação, filtros, atualização automática, claim por usuário, abandono controlado e acesso supervisionado para evitar vendas travadas.'; Accent = '#FFCA2C' },
    @{ X = 798; Y = 210; W = 726; H = 250; Title = 'Hierarquia comercial'; Body = 'Operações, base grupos, headcount por período, importação e exportação em XLSX, busca por CPF e vínculo automático na venda.'; Accent = '#20C5DE' },
    @{ X = 76; Y = 490; W = 690; H = 250; Title = 'Catálogo de produtos'; Body = 'Gestão por período, B2B/B2C, importação e exportação em lote, alteração pontual e recálculo seletivo sobre vendas finalizadas.'; Accent = '#37A86D' },
    @{ X = 798; Y = 490; W = 726; H = 250; Title = 'Administração e segurança'; Body = 'Usuários com primeiro acesso controlado, inativação, reset de senha, visão regional ou personalizada e log de alteração por venda.'; Accent = '#091555' }
)
foreach ($card in $moduleCards) {
    Draw-ShadowCard -Graphics $g -X $card.X -Y $card.Y -Width $card.W -Height $card.H -Background '#FFFFFF' -Border '#D4E0EC'
    Fill-RoundRect -Graphics $g -Hex $card.Accent -X ($card.X + 26) -Y ($card.Y + 24) -Width 14 -Height 62 -Radius 7
    Draw-Text -Graphics $g -Text $card.Title -Hex '#2B475D' -X ($card.X + 60) -Y ($card.Y + 28) -Width ($card.W - 92) -Height 38 -Size 28 -Style 'Bold'
    Draw-Text -Graphics $g -Text $card.Body -Hex '#6A8098' -X ($card.X + 60) -Y ($card.Y + 86) -Width ($card.W - 92) -Height 110 -Size 21
}
Draw-Text -Graphics $g -Text 'Resultado: a operação sai do modo reativo e passa a trabalhar com processo, controle e capacidade de expansão.' -Hex '#4F677F' -X 80 -Y 782 -Width 1280 -Height 42 -Size 22 -Style 'Bold'
Draw-SlideNumber -Graphics $g -Number 4
$path = Join-Path $slidesDir 'slide04.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 5
$slide = New-SlideBitmap -Background '#091555' -Background2 '#132D88' -Dark
$g = $slide.Graphics
Draw-Text -Graphics $g -Text 'Governança, segurança e compliance' -Hex '#FFFFFF' -X 86 -Y 82 -Width 920 -Height 58 -Size 50 -Style 'Bold'
Draw-Text -Graphics $g -Text 'Controles implementados para reduzir risco operacional, garantir rastreabilidade e dar autonomia com segurança.' -Hex '#D6DFF8' -X 86 -Y 148 -Width 940 -Height 52 -Size 24
Draw-ShadowCard -Graphics $g -X 82 -Y 232 -Width 710 -Height 530 -Background '#111C5F' -Border '#263997'
Draw-Text -Graphics $g -Text 'Controle de acesso' -Hex '#FFFFFF' -X 116 -Y 266 -Width 280 -Height 32 -Size 28 -Style 'Bold'
Draw-Bullets -Graphics $g -Items @(
    'login por CPF e gestão centralizada de usuários',
    'primeiro acesso obriga criação de senha forte',
    'usuários inativos não entram no sistema',
    'perfis distintos: Administrador, Backoffice e Supervisor',
    'visão FULL, regional I e II ou personalizada por base grupo'
) -Hex '#D6DFF8' -X 118 -Y 324 -Width 560 -LineHeight 72 -Size 22
Draw-ShadowCard -Graphics $g -X 822 -Y 232 -Width 692 -Height 530 -Background '#111C5F' -Border '#263997'
Draw-Text -Graphics $g -Text 'Rastreabilidade operacional' -Hex '#FFFFFF' -X 856 -Y 266 -Width 360 -Height 32 -Size 28 -Style 'Bold'
Draw-Bullets -Graphics $g -Items @(
    'cada venda registra quem assumiu, alterou e finalizou',
    'supervisor consegue continuar ou soltar vendas quando necessário',
    'LGPD respeitada na fila: documento não fica exposto na operação',
    'upload de produtos e headcount mantém histórico por período',
    'filtro de escopo garante que cada usuário veja só o que deve operar'
) -Hex '#D6DFF8' -X 858 -Y 324 -Width 560 -LineHeight 72 -Size 22
Draw-Badge -Graphics $g -Text 'CPF' -Background '#FFCA2C' -Foreground '#3B2B00' -X 106 -Y 794 -Width 100 -Height 42
Draw-Badge -Graphics $g -Text 'PRIMEIRO ACESSO' -Background '#FFFFFF' -Foreground '#091555' -X 220 -Y 794 -Width 220 -Height 42
Draw-Badge -Graphics $g -Text 'VISÃO PERSONALIZADA' -Background '#1CC5DF' -Foreground '#062738' -X 454 -Y 794 -Width 246 -Height 42
Draw-Badge -Graphics $g -Text 'LOG DE ALTERAÇÃO' -Background '#E1F3E8' -Foreground '#146A43' -X 714 -Y 794 -Width 210 -Height 42
Draw-SlideNumber -Graphics $g -Number 5 -Dark
$path = Join-Path $slidesDir 'slide05.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 6
$slide = New-SlideBitmap -Background '#F4F9FE' -Background2 '#FFFFFF'
$g = $slide.Graphics
Draw-TopRibbon -Graphics $g -Title 'Desempenho operacional para escala'
$perfCards = @(
    @{ X = 84; Y = 214; Title = 'Atualização quase em tempo real'; Body = 'A fila é atualizada automaticamente para reduzir conflito entre operadores.'; Accent = '#20C5DE' },
    @{ X = 554; Y = 214; Title = 'Paginação e filtros'; Body = 'Listagens pensadas para volume, com busca por status, data, tipo e operação.'; Accent = '#091555' },
    @{ X = 1024; Y = 214; Title = 'Deduplicação por venda'; Body = 'Novos uploads não mexem no que já entrou; o Código da Venda sustenta a integridade.'; Accent = '#FFCA2C' },
    @{ X = 84; Y = 494; Title = 'Prefill inteligente'; Body = 'CEP, hierarquia, produtos e dados do arquivo reduzem tempo de digitação.'; Accent = '#37A86D' },
    @{ X = 554; Y = 494; Title = 'Recalcular só o necessário'; Body = 'Mudanças de produto podem refletir apenas na faixa de datas escolhida.'; Accent = '#1CC5DF' },
    @{ X = 1024; Y = 494; Title = 'Base pronta para gestão'; Body = 'A sales_nexus concentra a venda final com estrutura consistente e histórica.'; Accent = '#091555' }
)
foreach ($card in $perfCards) {
    Draw-ShadowCard -Graphics $g -X $card.X -Y $card.Y -Width 420 -Height 220 -Background '#FFFFFF' -Border '#D6E2EE'
    Fill-RoundRect -Graphics $g -Hex $card.Accent -X ($card.X + 28) -Y ($card.Y + 26) -Width 52 -Height 52 -Radius 20
    Draw-Text -Graphics $g -Text $card.Title -Hex '#2B475D' -X ($card.X + 98) -Y ($card.Y + 28) -Width 282 -Height 70 -Size 24 -Style 'Bold'
    Draw-Text -Graphics $g -Text $card.Body -Hex '#6A8098' -X ($card.X + 28) -Y ($card.Y + 104) -Width 350 -Height 82 -Size 19
}
Draw-Badge -Graphics $g -Text 'Feito para operação recorrente' -Background '#091555' -Foreground '#FFFFFF' -X 84 -Y 786 -Width 330 -Height 42
Draw-Badge -Graphics $g -Text 'Sem perder governança' -Background '#FFCA2C' -Foreground '#3B2B00' -X 434 -Y 786 -Width 250 -Height 42
Draw-SlideNumber -Graphics $g -Number 6
$path = Join-Path $slidesDir 'slide06.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 7
$slide = New-SlideBitmap -Background '#091555' -Background2 '#162C84' -Dark
$g = $slide.Graphics
Draw-Text -Graphics $g -Text 'Versionamento comercial e de headcount' -Hex '#FFFFFF' -X 86 -Y 80 -Width 900 -Height 58 -Size 50 -Style 'Bold'
Draw-Text -Graphics $g -Text 'O CRM preserva o histórico do mês anterior e permite operar o mês atual sem reescrever o passado.' -Hex '#D6DFF8' -X 86 -Y 146 -Width 920 -Height 52 -Size 24
Draw-ShadowCard -Graphics $g -X 82 -Y 224 -Width 700 -Height 530 -Background '#111C5F' -Border '#263997'
Draw-Text -Graphics $g -Text 'Headcount por período' -Hex '#FFFFFF' -X 118 -Y 258 -Width 280 -Height 32 -Size 28 -Style 'Bold'
Draw-Badge -Graphics $g -Text '202603' -Background '#FFCA2C' -Foreground '#3B2B00' -X 118 -Y 314 -Width 116 -Height 42
Draw-Text -Graphics $g -Text 'Erick vinculado à base grupo VIGGO BARRA FUNDA II' -Hex '#D6DFF8' -X 258 -Y 318 -Width 420 -Height 38 -Size 22
Draw-Badge -Graphics $g -Text '202604' -Background '#1CC5DF' -Foreground '#05293A' -X 118 -Y 388 -Width 116 -Height 42
Draw-Text -Graphics $g -Text 'Mesmo vendedor já pode aparecer em outra estrutura, sem perder o histórico anterior' -Hex '#D6DFF8' -X 258 -Y 392 -Width 408 -Height 62 -Size 22
Draw-Text -Graphics $g -Text 'Chave operacional:' -Hex '#FFFFFF' -X 118 -Y 486 -Width 180 -Height 26 -Size 22 -Style 'Bold'
Draw-Badge -Graphics $g -Text 'CPF + PERÍODO HEADCOUNT' -Background '#FFFFFF' -Foreground '#091555' -X 118 -Y 524 -Width 330 -Height 44
Draw-ShadowCard -Graphics $g -X 814 -Y 224 -Width 704 -Height 530 -Background '#111C5F' -Border '#263997'
Draw-Text -Graphics $g -Text 'Catálogo de produtos por período' -Hex '#FFFFFF' -X 848 -Y 258 -Width 360 -Height 32 -Size 28 -Style 'Bold'
Draw-Badge -Graphics $g -Text '202603' -Background '#FFCA2C' -Foreground '#3B2B00' -X 848 -Y 314 -Width 116 -Height 42
Draw-Text -Graphics $g -Text 'Produto 600 MEGA - OFERTA PERSONALIZADO com valor de referência 120,00' -Hex '#D6DFF8' -X 988 -Y 318 -Width 430 -Height 66 -Size 22
Draw-Badge -Graphics $g -Text '202604' -Background '#1CC5DF' -Foreground '#05293A' -X 848 -Y 406 -Width 116 -Height 42
Draw-Text -Graphics $g -Text 'Novo valor pode ser importado em lote sem reprocessar períodos anteriores' -Hex '#D6DFF8' -X 988 -Y 410 -Width 430 -Height 62 -Size 22
Draw-Text -Graphics $g -Text 'Benefício executivo:' -Hex '#FFFFFF' -X 848 -Y 506 -Width 210 -Height 28 -Size 22 -Style 'Bold'
Draw-Text -Graphics $g -Text 'mais flexibilidade comercial sem comprometer histórico, comissão, auditoria ou análise futura.' -Hex '#D6DFF8' -X 848 -Y 542 -Width 500 -Height 80 -Size 22
Draw-SlideNumber -Graphics $g -Number 7 -Dark
$path = Join-Path $slidesDir 'slide07.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 8
$slide = New-SlideBitmap -Background '#F2F8FD' -Background2 '#FFFFFF'
$g = $slide.Graphics
Draw-TopRibbon -Graphics $g -Title 'Valor executivo para a diretoria'
$valueCards = @(
    @{ X = 84; Y = 218; W = 330; H = 190; Title = 'Velocidade'; Body = 'Mais produtividade para o backoffice, com menos esforço manual e mais capacidade de absorver volume.'; Accent = '#FFCA2C' },
    @{ X = 432; Y = 218; W = 330; H = 190; Title = 'Governança'; Body = 'Cada ação fica rastreada por usuário, com regras de acesso aderentes à operação.'; Accent = '#091555' },
    @{ X = 780; Y = 218; W = 330; H = 190; Title = 'Qualidade'; Body = 'Menos inconsistência de input com dados pré-preenchidos, validações e fluxo guiado.'; Accent = '#37A86D' },
    @{ X = 1128; Y = 218; W = 396; H = 190; Title = 'Escalabilidade'; Body = 'O CRM já nasce preparado para novos períodos, novas bases, novos produtos e mais usuários.'; Accent = '#20C5DE' }
)
foreach ($card in $valueCards) {
    Draw-ShadowCard -Graphics $g -X $card.X -Y $card.Y -Width $card.W -Height $card.H -Background '#FFFFFF' -Border '#D4E0EC'
    Fill-RoundRect -Graphics $g -Hex $card.Accent -X ($card.X + 24) -Y ($card.Y + 24) -Width 14 -Height 50 -Radius 7
    Draw-Text -Graphics $g -Text $card.Title -Hex '#2B475D' -X ($card.X + 50) -Y ($card.Y + 22) -Width ($card.W - 74) -Height 34 -Size 28 -Style 'Bold'
    Draw-Text -Graphics $g -Text $card.Body -Hex '#6A8098' -X ($card.X + 24) -Y ($card.Y + 82) -Width ($card.W - 48) -Height 86 -Size 19
}
Draw-ShadowCard -Graphics $g -X 84 -Y 454 -Width 1440 -Height 284 -Background '#0F1B5B' -Border '#213497'
Draw-Text -Graphics $g -Text 'O que a diretoria passa a ganhar com o Sales Nexus' -Hex '#FFFFFF' -X 122 -Y 492 -Width 860 -Height 40 -Size 32 -Style 'Bold'
Draw-Bullets -Graphics $g -Items @(
    'controle central da fila e visibilidade do que está sendo tratado',
    'capacidade de medir produtividade, qualidade e gargalos com mais precisão',
    'base única e auditável para evolução de indicadores, BI e tomada de decisão',
    'redução do risco operacional quando houver troca de headcount, catálogo ou regional'
) -Hex '#D6DFF8' -X 128 -Y 560 -Width 820 -LineHeight 56 -Size 22
Draw-Badge -Graphics $g -Text 'PRONTO PARA EVOLUIR PARA INDICADORES E BI' -Background '#FFCA2C' -Foreground '#3B2B00' -X 1018 -Y 574 -Width 420 -Height 48
Draw-Text -Graphics $g -Text 'O projeto deixa de ser apenas uma tela operacional e passa a ser um ativo de gestão.' -Hex '#FFFFFF' -X 1020 -Y 646 -Width 410 -Height 80 -Size 24 -Style 'Bold'
Draw-SlideNumber -Graphics $g -Number 8
$path = Join-Path $slidesDir 'slide08.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

# Slide 9
$slide = New-SlideBitmap -Background '#091555' -Background2 '#122879' -Dark
$g = $slide.Graphics
if ($logoImage) {
    $g.DrawImage($logoImage, [System.Drawing.RectangleF]::new(86, 62, 170, 66))
}
Draw-Text -Graphics $g -Text 'Próximos passos recomendados' -Hex '#FFFFFF' -X 86 -Y 150 -Width 760 -Height 60 -Size 52 -Style 'Bold'
Draw-Text -Graphics $g -Text 'O sistema já demonstra aderência operacional. O próximo movimento é transformar a solução em plataforma oficial da operação.' -Hex '#D6DFF8' -X 86 -Y 222 -Width 880 -Height 60 -Size 24
$roadmap = @(
    @{ No='1'; Title='Homologar regras finais'; Text='Fechar permissões por perfil, regras de negócio e campos finais da auditoria.' },
    @{ No='2'; Title='Consolidar cadastros'; Text='Validar base de headcount, catálogo do mês e usuários por escopo operacional.' },
    @{ No='3'; Title='Piloto assistido'; Text='Operar uma ou mais bases com acompanhamento, medindo produtividade e ajustes.' },
    @{ No='4'; Title='Escalar e medir'; Text='Expandir a operação e iniciar indicadores gerenciais sobre a base final.' }
)
$ry = 330
foreach ($item in $roadmap) {
    Draw-ShadowCard -Graphics $g -X 92 -Y $ry -Width 900 -Height 112 -Background '#121E62' -Border '#273A98'
    Draw-Badge -Graphics $g -Text $item.No -Background '#FFCA2C' -Foreground '#3B2B00' -X 118 -Y ($ry + 32) -Width 48 -Height 40
    Draw-Text -Graphics $g -Text $item.Title -Hex '#FFFFFF' -X 192 -Y ($ry + 22) -Width 320 -Height 30 -Size 26 -Style 'Bold'
    Draw-Text -Graphics $g -Text $item.Text -Hex '#D6DFF8' -X 192 -Y ($ry + 58) -Width 720 -Height 34 -Size 19
    $ry += 128
}
Draw-ShadowCard -Graphics $g -X 1050 -Y 286 -Width 420 -Height 396 -Background '#FFFFFF' -Border '#D4E0EC'
Draw-Text -Graphics $g -Text 'Mensagem final' -Hex '#2B475D' -X 1088 -Y 324 -Width 220 -Height 32 -Size 28 -Style 'Bold'
Draw-Text -Graphics $g -Text 'O Sales Nexus CRM reúne operação, segurança e versionamento em um único ambiente. Isso reduz risco, acelera a fila e cria base confiável para gestão.' -Hex '#5F738A' -X 1088 -Y 384 -Width 340 -Height 150 -Size 22
Draw-Badge -Graphics $g -Text 'DECISÃO RECOMENDADA' -Background '#091555' -Foreground '#FFFFFF' -X 1088 -Y 560 -Width 250 -Height 40
Draw-Text -Graphics $g -Text 'Avançar para implantação assistida e transformar o CRM em padrão operacional da empresa.' -Hex '#2B475D' -X 1088 -Y 618 -Width 320 -Height 100 -Size 24 -Style 'Bold'
Draw-SlideNumber -Graphics $g -Number 9 -Dark
$path = Join-Path $slidesDir 'slide09.png'
Save-Slide -Slide $slide -Path $path
$slidePaths.Add($path)

if ($logoImage) {
    $logoImage.Dispose()
}

$builderSource = @"
using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text;
using DocumentFormat.OpenXml.Packaging;
using DocumentFormat.OpenXml.Presentation;
using DocumentFormat.OpenXml.Validation;

public static class SalesNexusPitchDeckBuilder
{
    public static void Build(string outputPath, string[] imagePaths, string[] slideTitles)
    {
        if (File.Exists(outputPath))
        {
            File.Delete(outputPath);
        }

        using (PresentationDocument document = PresentationDocument.Create(outputPath, DocumentFormat.OpenXml.PresentationDocumentType.Presentation))
        {
            document.PackageProperties.Creator = "OpenAI Codex";
            document.PackageProperties.LastModifiedBy = "OpenAI Codex";
            document.PackageProperties.Title = "Sales Nexus CRM - Apresentação executiva";
            document.PackageProperties.Subject = "Proposta executiva do CRM Sales Nexus";
            document.PackageProperties.Description = "Apresentação executiva para diretoria sobre o CRM Sales Nexus.";
            document.PackageProperties.Created = DateTime.UtcNow;
            document.PackageProperties.Modified = DateTime.UtcNow;

            PresentationPart presentationPart = document.AddPresentationPart();
            SlideMasterPart slideMasterPart = presentationPart.AddNewPart<SlideMasterPart>("rId1");
            SlideLayoutPart slideLayoutPart = slideMasterPart.AddNewPart<SlideLayoutPart>("rId1");
            ThemePart themePart = slideMasterPart.AddNewPart<ThemePart>("rId2");

            WriteXml(slideLayoutPart, SlideLayoutXml());
            WriteXml(themePart, ThemeXml());
            WriteXml(slideMasterPart, SlideMasterXml());

            Presentation presentation = new Presentation();
            SlideMasterIdList masterIdList = new SlideMasterIdList();
            masterIdList.Append(new SlideMasterId() { Id = 2147483648U, RelationshipId = presentationPart.GetIdOfPart(slideMasterPart) });
            presentation.Append(masterIdList);

            SlideIdList slideIdList = new SlideIdList();
            uint slideId = 256U;

            for (int i = 0; i < imagePaths.Length; i++)
            {
                SlidePart slidePart = presentationPart.AddNewPart<SlidePart>("rId" + (i + 2).ToString());
                WriteXml(slidePart, SlideXml(slideTitles[i]));
                slidePart.AddPart(slideLayoutPart, "rId1");
                ImagePart imagePart = slidePart.AddImagePart(ImagePartType.Png, "rId2");

                using (FileStream imageStream = File.OpenRead(imagePaths[i]))
                {
                    imagePart.FeedData(imageStream);
                }

                slideIdList.Append(new SlideId()
                {
                    Id = slideId++,
                    RelationshipId = presentationPart.GetIdOfPart(slidePart)
                });
            }

            presentation.Append(slideIdList);
            presentation.SlideSize = new SlideSize() { Cx = 12192000, Cy = 6858000 };
            presentation.NotesSize = new NotesSize() { Cx = 6858000, Cy = 9144000 };
            presentation.DefaultTextStyle = new DefaultTextStyle();

            presentationPart.Presentation = presentation;
            presentationPart.Presentation.Save();

            Validate(document);
        }
    }

    private static void WriteXml(OpenXmlPart part, string xml)
    {
        using (Stream stream = part.GetStream(FileMode.Create, FileAccess.Write))
        using (StreamWriter writer = new StreamWriter(stream, new UTF8Encoding(false)))
        {
            writer.Write(xml);
        }
    }

    private static void Validate(PresentationDocument document)
    {
        OpenXmlValidator validator = new OpenXmlValidator();
        List<ValidationErrorInfo> errors = validator.Validate(document).Take(10).ToList();

        if (errors.Count > 0)
        {
            throw new Exception("OpenXML validation failed: " + string.Join(" | ", errors.Select(e => e.Description)));
        }
    }

    private static string SlideXml(string title)
    {
        return @"<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?>
<p:sld xmlns:a=""http://schemas.openxmlformats.org/drawingml/2006/main"" xmlns:r=""http://schemas.openxmlformats.org/officeDocument/2006/relationships"" xmlns:p=""http://schemas.openxmlformats.org/presentationml/2006/main"">
  <p:cSld name=""" + Escape(title) + @""">
    <p:spTree>
      <p:nvGrpSpPr>
        <p:cNvPr id=""1"" name="""" />
        <p:cNvGrpSpPr />
        <p:nvPr />
      </p:nvGrpSpPr>
      <p:grpSpPr>
        <a:xfrm>
          <a:off x=""0"" y=""0"" />
          <a:ext cx=""0"" cy=""0"" />
          <a:chOff x=""0"" y=""0"" />
          <a:chExt cx=""0"" cy=""0"" />
        </a:xfrm>
      </p:grpSpPr>
      <p:pic>
        <p:nvPicPr>
          <p:cNvPr id=""2"" name=""" + Escape(title) + @""" />
          <p:cNvPicPr>
            <a:picLocks noChangeAspect=""1"" />
          </p:cNvPicPr>
          <p:nvPr />
        </p:nvPicPr>
        <p:blipFill>
          <a:blip r:embed=""rId2"" />
          <a:stretch>
            <a:fillRect />
          </a:stretch>
        </p:blipFill>
        <p:spPr>
          <a:xfrm>
            <a:off x=""0"" y=""0"" />
            <a:ext cx=""12192000"" cy=""6858000"" />
          </a:xfrm>
          <a:prstGeom prst=""rect"">
            <a:avLst />
          </a:prstGeom>
        </p:spPr>
      </p:pic>
    </p:spTree>
  </p:cSld>
  <p:clrMapOvr>
    <a:masterClrMapping />
  </p:clrMapOvr>
</p:sld>";
    }

    private static string SlideLayoutXml()
    {
        return @"<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?>
<p:sldLayout xmlns:a=""http://schemas.openxmlformats.org/drawingml/2006/main"" xmlns:r=""http://schemas.openxmlformats.org/officeDocument/2006/relationships"" xmlns:p=""http://schemas.openxmlformats.org/presentationml/2006/main"" type=""blank"" preserve=""1"">
  <p:cSld name=""Blank"">
    <p:spTree>
      <p:nvGrpSpPr>
        <p:cNvPr id=""1"" name="""" />
        <p:cNvGrpSpPr />
        <p:nvPr />
      </p:nvGrpSpPr>
      <p:grpSpPr>
        <a:xfrm>
          <a:off x=""0"" y=""0"" />
          <a:ext cx=""0"" cy=""0"" />
          <a:chOff x=""0"" y=""0"" />
          <a:chExt cx=""0"" cy=""0"" />
        </a:xfrm>
      </p:grpSpPr>
    </p:spTree>
  </p:cSld>
  <p:clrMapOvr>
    <a:masterClrMapping />
  </p:clrMapOvr>
</p:sldLayout>";
    }

    private static string SlideMasterXml()
    {
        return @"<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?>
<p:sldMaster xmlns:a=""http://schemas.openxmlformats.org/drawingml/2006/main"" xmlns:r=""http://schemas.openxmlformats.org/officeDocument/2006/relationships"" xmlns:p=""http://schemas.openxmlformats.org/presentationml/2006/main"">
  <p:cSld name=""Sales Nexus Master"">
    <p:spTree>
      <p:nvGrpSpPr>
        <p:cNvPr id=""1"" name="""" />
        <p:cNvGrpSpPr />
        <p:nvPr />
      </p:nvGrpSpPr>
      <p:grpSpPr>
        <a:xfrm>
          <a:off x=""0"" y=""0"" />
          <a:ext cx=""0"" cy=""0"" />
          <a:chOff x=""0"" y=""0"" />
          <a:chExt cx=""0"" cy=""0"" />
        </a:xfrm>
      </p:grpSpPr>
    </p:spTree>
  </p:cSld>
  <p:clrMap bg1=""lt1"" tx1=""dk1"" bg2=""lt2"" tx2=""dk2"" accent1=""accent1"" accent2=""accent2"" accent3=""accent3"" accent4=""accent4"" accent5=""accent5"" accent6=""accent6"" hlink=""hlink"" folHlink=""folHlink"" />
  <p:sldLayoutIdLst>
    <p:sldLayoutId id=""2147483649"" r:id=""rId1"" />
  </p:sldLayoutIdLst>
  <p:txStyles>
    <p:titleStyle>
      <a:lvl1pPr algn=""l"">
        <a:defRPr sz=""4400"" kern=""1200"" />
      </a:lvl1pPr>
    </p:titleStyle>
    <p:bodyStyle>
      <a:lvl1pPr marL=""342900"" indent=""-342900"">
        <a:buFont typeface=""Segoe UI"" />
        <a:buChar char=""•"" />
        <a:defRPr sz=""3000"" kern=""1200"" />
      </a:lvl1pPr>
    </p:bodyStyle>
    <p:otherStyle>
      <a:defPPr>
        <a:defRPr lang=""pt-BR"" />
      </a:defPPr>
    </p:otherStyle>
  </p:txStyles>
</p:sldMaster>";
    }

    private static string ThemeXml()
    {
        return @"<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?>
<a:theme xmlns:a=""http://schemas.openxmlformats.org/drawingml/2006/main"" name=""Sales Nexus Theme"">
  <a:themeElements>
    <a:clrScheme name=""Sales Nexus"">
      <a:dk1><a:srgbClr val=""1F1F1F"" /></a:dk1>
      <a:lt1><a:srgbClr val=""FFFFFF"" /></a:lt1>
      <a:dk2><a:srgbClr val=""2B475D"" /></a:dk2>
      <a:lt2><a:srgbClr val=""EBF3FB"" /></a:lt2>
      <a:accent1><a:srgbClr val=""091555"" /></a:accent1>
      <a:accent2><a:srgbClr val=""20C5DE"" /></a:accent2>
      <a:accent3><a:srgbClr val=""FFCA2C"" /></a:accent3>
      <a:accent4><a:srgbClr val=""37A86D"" /></a:accent4>
      <a:accent5><a:srgbClr val=""7C90A3"" /></a:accent5>
      <a:accent6><a:srgbClr val=""335CFF"" /></a:accent6>
      <a:hlink><a:srgbClr val=""0563C1"" /></a:hlink>
      <a:folHlink><a:srgbClr val=""954F72"" /></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name=""Sales Nexus Fonts"">
      <a:majorFont><a:latin typeface=""Segoe UI"" /><a:ea typeface="""" /><a:cs typeface="""" /></a:majorFont>
      <a:minorFont><a:latin typeface=""Segoe UI"" /><a:ea typeface="""" /><a:cs typeface="""" /></a:minorFont>
    </a:fontScheme>
    <a:fmtScheme name=""Sales Nexus Format"">
      <a:fillStyleLst>
        <a:solidFill><a:schemeClr val=""phClr"" /></a:solidFill>
        <a:gradFill rotWithShape=""1""><a:gsLst><a:gs pos=""0""><a:schemeClr val=""phClr""><a:tint val=""50000"" /><a:satMod val=""300000"" /></a:schemeClr></a:gs><a:gs pos=""35000""><a:schemeClr val=""phClr""><a:tint val=""37000"" /><a:satMod val=""300000"" /></a:schemeClr></a:gs><a:gs pos=""100000""><a:schemeClr val=""phClr""><a:tint val=""15000"" /><a:satMod val=""350000"" /></a:schemeClr></a:gs></a:gsLst><a:lin ang=""16200000"" scaled=""1"" /></a:gradFill>
        <a:gradFill rotWithShape=""1""><a:gsLst><a:gs pos=""0""><a:schemeClr val=""phClr""><a:shade val=""51000"" /><a:satMod val=""130000"" /></a:schemeClr></a:gs><a:gs pos=""80000""><a:schemeClr val=""phClr""><a:shade val=""93000"" /><a:satMod val=""130000"" /></a:schemeClr></a:gs><a:gs pos=""100000""><a:schemeClr val=""phClr""><a:shade val=""94000"" /><a:satMod val=""135000"" /></a:schemeClr></a:gs></a:gsLst><a:lin ang=""16200000"" scaled=""0"" /></a:gradFill>
      </a:fillStyleLst>
      <a:lnStyleLst>
        <a:ln w=""9525"" cap=""flat"" cmpd=""sng"" algn=""ctr""><a:solidFill><a:schemeClr val=""phClr"" /></a:solidFill><a:prstDash val=""solid"" /></a:ln>
        <a:ln w=""25400"" cap=""flat"" cmpd=""sng"" algn=""ctr""><a:solidFill><a:schemeClr val=""phClr"" /></a:solidFill><a:prstDash val=""solid"" /></a:ln>
        <a:ln w=""38100"" cap=""flat"" cmpd=""sng"" algn=""ctr""><a:solidFill><a:schemeClr val=""phClr"" /></a:solidFill><a:prstDash val=""solid"" /></a:ln>
      </a:lnStyleLst>
      <a:effectStyleLst><a:effectStyle><a:effectLst /></a:effectStyle><a:effectStyle><a:effectLst /></a:effectStyle><a:effectStyle><a:effectLst /></a:effectStyle></a:effectStyleLst>
      <a:bgFillStyleLst>
        <a:solidFill><a:schemeClr val=""phClr"" /></a:solidFill>
        <a:solidFill><a:schemeClr val=""phClr""><a:tint val=""95000"" /><a:satMod val=""170000"" /></a:schemeClr></a:solidFill>
        <a:gradFill rotWithShape=""1""><a:gsLst><a:gs pos=""0""><a:schemeClr val=""phClr""><a:tint val=""93000"" /><a:satMod val=""150000"" /><a:shade val=""98000"" /></a:schemeClr></a:gs><a:gs pos=""50000""><a:schemeClr val=""phClr""><a:tint val=""98000"" /><a:satMod val=""130000"" /><a:shade val=""90000"" /></a:schemeClr></a:gs><a:gs pos=""100000""><a:schemeClr val=""phClr""><a:shade val=""63000"" /><a:satMod val=""120000"" /></a:schemeClr></a:gs></a:gsLst><a:lin ang=""16200000"" scaled=""0"" /></a:gradFill>
      </a:bgFillStyleLst>
    </a:fmtScheme>
  </a:themeElements>
  <a:objectDefaults />
  <a:extraClrSchemeLst />
</a:theme>";
    }

    private static string Escape(string value)
    {
        return value.Replace("&", "&amp;").Replace("\"", "&quot;").Replace("<", "&lt;").Replace(">", "&gt;");
    }
}
"@

Add-Type -ReferencedAssemblies @('WindowsBase', $openXmlDll) -TypeDefinition $builderSource -Language CSharp
[System.Reflection.Assembly]::LoadFrom($openXmlDll) | Out-Null
[SalesNexusPitchDeckBuilder]::Build($OutputPath, $slidePaths.ToArray(), $slideTitles)
Write-Output ("PRESENTATION_CREATED: " + $OutputPath)
