<?php
error_reporting(0);
class Paper
{

    public $tables;
    public $pmid;
    private $file_json;
    public $annotations = [];
    public $relations = [];

    function __construct($file_input)
    {
        $this->file_json = file_get_contents($file_input);
        $this->file_json = json_decode($this->file_json, true, 512, JSON_UNESCAPED_UNICODE);
        $this->pmid = $_GET['pmcid'];
        $this->tables = [];
        foreach ($this->file_json["documents"] as $table_json) {
            $new_table = new Table($table_json);
            array_push($this->tables, $new_table);
            $this->collect_table_annotations($new_table);
            $this->relations = array_merge($this->relations, $new_table->table_relations);
        }
    }

    private function collect_table_annotations($table)
    {
        if ($table->title_annotations)
            $this->annotations = array_merge($this->annotations, $table->title_annotations);
        if ($table->caption_annotations)
            $this->annotations = array_merge($this->annotations, $table->caption_annotations);
        if ($table->table_annotations)
            $this->annotations = array_merge($this->annotations, $table->table_annotations);
        if ($table->footer_annotations)
            $this->annotations = array_merge($this->annotations, $table->footer_annotations);
    }

    public function outputTables()
    {
        $return_str = "<a href=https://www.ncbi.nlm.nih.gov/pmc/articles/{$this->pmid} target=_newWindow>{$this->pmid}</a>";
        foreach ($this->tables as $table) {
            $return_str .= $table->output_table();
        }
        return $return_str;
    }
}

class Table
{

    public $table_num = 0;
    public $headers;
    public $data_sections = [];
    public $caption;
    public $caption_annotations;
    public $title;
    public $title_annotations = [];
    public $table_annotations = [];
    public $table_relations = [];
    public $annotation_ids = [];
    public $footer;
    public $footer_annotations = [];
    public $max_col_size = 1;
    private $caption_relations;
    private $caption_annots;
    private $title_relations;
    private $footer_relations;

    private $piped_indexes = [];

    function __construct($json_data)
    {
        $this->table_num = $json_data["id"];
        $this->data_sections = $this->__get_data_sections($json_data);
        $this->headers = $this->__expand_headers($json_data["passages"][2]["column_headings"]);
        $this->caption = $json_data["passages"][1]["text"];
        $this->caption_annots = $json_data["passages"][1]["annotations"]; // this line is problematic?
        $this->caption_relations = $json_data["passages"][1]["relations"];
        $this->title = $json_data["passages"][0]["text"];
        $this->title_annotations = $json_data["passages"][0]["annotations"];
        $this->title_relations = $json_data["passages"][1]["relations"];
        $this->footer = $json_data["passages"][3]["text"];
        $this->footer_annotations = $json_data["passages"][3]["annotations"];
        $this->footer_relations = $json_data["passages"][1]["relations"];
    }

    private function __expand_headers($headers)
    {
        // Expand piped columns to appear more like the original article
        $new_headers = []; // 2D array for rows and columns
        $used_strings = [];
        $max_row_count = sizeof($headers);
        for ($i = 0; $i < sizeof($headers); $i++) { // $i == cell
            $used_strings[] = [];
            $header = $headers[$i];//current header
            if (strpos($header["cell_text"], "|")) {
                $pipe_split = explode("|", $header["cell_text"]); // split by pipe character
                $this->piped_indexes[] = $i;
                if (sizeof($pipe_split) > $max_row_count)
                    $max_row_count = sizeof($pipe_split);
                for ($o = 0; $o < sizeof($pipe_split); $o++) { // iterate over each string
                    $sub_string = $pipe_split[$o];
                    $new_header = $header;
                    if (in_array($sub_string, $used_strings[$i])) // ignore duplicates after first occurrence
                        $new_header["cell_text"] = "";
                    else {
                        $used_strings[$i][] = $sub_string; // log the used string
                        // create new header record with new substring
                        $new_header["cell_text"] = $sub_string;
                    }
                    $new_headers[$o][$i] = $new_header; // add the new header row/cell
                }
            } else {
                $new_headers[0][$i] = $header; // no split needed, add the current header cell to the top row
            }
        }
        // fill any gaps in rows with blank cells
        for ($i = 0; $i < sizeof($new_headers); $i++) {
            if (sizeof($new_headers[$i]) < sizeof($headers)) {
                $blank_cell = $new_headers[$i][0];
                $blank_cell["cell_text"] = "";
                for ($o = 0; $o < sizeof($headers); $o++) {
                    if (!array_key_exists($o, $new_headers[$i])) {
                        $new_headers[$i][$o] = $blank_cell;
                    }
                }
            }
        }
        return $new_headers;
    }

    private function __get_data_sections($json_data)
    {
        $data_sections = [];
        foreach ($json_data["passages"][2]["data_section"] as $section) {
            $rows = [];
            foreach ($section["data_rows"] as $row) {
                if (sizeof($row) > $this->max_col_size)
                    $this->max_col_size = sizeof($row);
                $new_row = [];
                foreach ($row as $cell) {
                    $new_row[] = [$cell["cell_id"], $cell["cell_text"]];
                }
                $rows[] = $new_row;
            }
            $data_sections[] = [$section["text"], $rows];
        }
        foreach ($json_data["passages"][2]["annotations"] as $annotation) {
            $this->table_annotations[] = $annotation;
            $this->annotation_ids[] = $annotation["locations"][0]["cell_id"];
        }
        foreach ($json_data["passages"][2]["relations"] as $relation) {
            $this->table_relations[] = $relation;
        }
        return $data_sections;
    }

    private function __get_headers_output()
    {
        $return_str = "";
        foreach ($this->headers as $row) {
            $return_str .= "<tr>";
            for ($i = 0; $i < sizeof($row); $i++) {
                $cell = $row[$i];
                if (in_array($cell["cell_id"], $this->annotation_ids)) {
                    $cell_annot_id = "";
                    foreach ($this->table_annotations as $annot) {
                        if ($annot["locations"][0]["cell_id"] == $cell["cell_id"]) {
                            $cell_annot_id = $annot["id"];
                            break;
                        }
                    }
                    $cell_content = "<div class='annotation' data-annot-id='{$cell_annot_id}'>{$cell["cell_text"]}</div>";
                } else {
                    $cell_content = "<div data-annot-id='{$cell["cell_id"]}'>{$cell["cell_text"]}</div>";
                }
                // calculate col span.
                $col_span = 1;
                // look ahead at remaining columns in row
                for ($o = $i + 1; $o < sizeof($row); $o++) {
                    if (($cell["cell_text"] == $row[$o]["cell_text"]) || (!in_array($o, $this->piped_indexes)
                            && !in_array($i, $this->piped_indexes)
                            && ($cell["cell_text"] == "" || $row[$o]["cell_text"] == ""))) {
                        $col_span++;
                    } else {
                        break;
                    }
                }
                $return_str .= "<th colspan='{$col_span}'>
                        $cell_content
                    </th>";
                // skip detected duplicate headers
                $i += $col_span - 1;
            }
            $return_str .= "</tr>";
        }
        return $return_str;
    }

    private function __get_caption_output()
    {
        if ($this->caption_annotations) {
            $added_char_length = 0;
            foreach ($this->caption_annotations as $annot) {
                $replace_str = "<div class='annotation' data-annot-id='{$annot['id']}'>{$annot['text']}</div>";
                $this->caption = substr_replace($this->caption, $replace_str, $annot["locations"][0]["offset"] + $added_char_length, $annot["locations"][0]["length"]);
                $added_char_length += (strlen($replace_str) - $annot["locations"][0]["length"]);
            }
        }
        return "<caption style='text-align:left'><br/><b>{$this->title}</b><p>{$this->caption}</p></caption>";
    }

    private function __get_table_num_output()
    {
        return "
            <p>Auto-CORPus id: {$this->table_num}</p>
        ";
    }

    private function __get_sections_output()
    {
        $return_str = "";
        foreach ($this->data_sections as $section) {
            if ($section[0]) {
                $header_count = $this->max_col_size;
                $return_str .= "<tr><td colspan='{$header_count}'><b>{$section[0]}<b></td></tr>";
            }
            foreach ($section as $new_section) {
                foreach($new_section as $row) {
                    $return_str .= "<tr>";
                    for($i = 0; $i < sizeof($row); $i++) {
                        $cell = $row[$i];
                        if (in_array($cell[0], $this->annotation_ids)) {
                            $cell_annot_id = "";
                            foreach ($this->table_annotations as $annot) {
                                if ($annot["locations"][0]["cell_id"] == $cell[0]) {
                                    $cell_annot_id = $annot["id"];
                                    break;
                                }
                            }
                            $cell_content = "<div class='annotation' data-annot-id='{$cell_annot_id}'>{$cell[1]}</div>";
                        } else {
                            $cell_content = "<div data-annot-id='{$cell[0]}'>{$cell[1]}</div>";
                        }
                        $return_str .= "<td>
                            $cell_content
                        </td>";
                    }
                    $return_str .= "</tr>";
                }
            }
        }
        return $return_str;
    }

    public function output_table()
    {
        $return_str = $this->__get_table_num_output();
        $return_str .= $this->__get_caption_output();
        $return_str .= "<div class='table-container'><table border=1 width='80%'><thead>";
        $return_str .= $this->__get_headers_output();
        $return_str .= "</thead><tbody>";
        $return_str .= $this->__get_sections_output();
        $return_str .= "</tbody></table></div>";
        if ($this->table_footer) {
            $return_str .= "<p>{$this->table_footer}</p>";
        }
        return $return_str;
    }
}

function mb_substr_replace($original, $replacement, $position, $length)
{
    $startString = mb_substr($original, 0, $position, "UTF-8");
    $endString = mb_substr($original, $position + $length, mb_strlen($original), "UTF-8");

    $out = $startString . $replacement . $endString;

    return $out;
}
