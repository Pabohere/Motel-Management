<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews>
    <sheetFormatPr defaultRowHeight="75"/>
    <cols><col min="1" max="10" width="12" customWidth="1"/></cols>
    <sheetData>
        <row r="1" ht="24" customHeight="1"><c r="A1" s="28" t="inlineStr"><is><t>{{ $company }}</t></is></c></row>
        <row r="2" ht="24" customHeight="1"><c r="A2" s="28" t="inlineStr"><is><t>NOTES TO THE MONTHLY FINANCIAL STATEMENTS</t></is></c></row>
        @foreach($notes as $index => $note)
        <row r="{{ $index + 3 }}" ht="80" customHeight="1"><c r="A{{ $index + 3 }}" s="60" t="inlineStr"><is><t>{{ ($index + 1).'. '.$note }}</t></is></c></row>
        @endforeach
    </sheetData>
    <mergeCells count="{{ count($notes) + 2 }}">@for($row = 1; $row <= count($notes) + 2; $row++)<mergeCell ref="A{{ $row }}:J{{ $row }}"/>@endfor</mergeCells>
    <pageMargins left="0.5" right="0.5" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>
    <pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="1"/>
</worksheet>
