import json
from urllib.parse import quote



class Paper:
    def __init__(self, file_input: str, pmcid: str):
        with open(file_input, encoding="utf-8") as f:
            self.file_json = json.load(f)
        self.pmid = pmcid
        self.tables = []
        self.annotations = []
        self.relations = []

        for table_json in self.file_json["documents"]:
            new_table = Table(table_json)
            self.tables.append(new_table)
            self._collect_table_annotations(new_table)
            self.relations.extend(new_table.table_relations)

    def _collect_table_annotations(self, table):
        if table.title_annotations:
            self.annotations.extend(table.title_annotations)
        if table.caption_annotations:
            self.annotations.extend(table.caption_annotations)
        if table.table_annotations:
            self.annotations.extend(table.table_annotations)
        if table.footer_annotations:
            self.annotations.extend(table.footer_annotations)

    def output_tables(self) -> str:
        link = f'<a href="https://www.ncbi.nlm.nih.gov/pmc/articles/{quote(self.pmid)}" target="_blank">{self.pmid}</a>'
        return link + ''.join([table.output_table() for table in self.tables])

class Table:
    def __init__(self, json_data):
        self.table_num = json_data.get("id", "unknown")
        self.piped_indexes = []
        self.max_col_size = 1
        self.annotation_ids = []

        passages = json_data.get("passages", [])
        
        # Safe extraction helper
        def get_passage(idx):
            return passages[idx] if len(passages) > idx else {}

        title_passage = get_passage(0)
        caption_passage = get_passage(1)
        table_passage = get_passage(2)
        footer_passage = get_passage(3)

        # Title
        self.title = title_passage.get("text", "")
        self.title_annotations = title_passage.get("annotations", [])

        # Caption
        self.caption = caption_passage.get("text", "")
        self.caption_annotations = caption_passage.get("annotations", [])
        self.caption_relations = caption_passage.get("relations", [])

        # Footer
        self.footer = footer_passage.get("text", "")
        self.footer_annotations = footer_passage.get("annotations", [])
        self.footer_relations = footer_passage.get("relations", [])

        # Headers
        self.headers = self._expand_headers(table_passage.get("column_headings", []))
        self.table_annotations = table_passage.get("annotations", [])
        self.table_relations = table_passage.get("relations", [])
        self.annotation_ids = [a["locations"][0]["cell_id"] for a in self.table_annotations]

        # Data sections
        self.data_sections = self._get_data_sections(table_passage)



    def _expand_headers(self, headers):
        new_headers = []
        used_strings = []
        max_row_count = len(headers)
        for i, header in enumerate(headers):
            used_strings.append([])
            if '|' in header["cell_text"]:
                pipe_split = header["cell_text"].split('|')
                self.piped_indexes.append(i)
                max_row_count = max(max_row_count, len(pipe_split))
                for o, sub_string in enumerate(pipe_split):
                    new_header = header.copy()
                    if sub_string in used_strings[i]:
                        new_header["cell_text"] = ""
                    else:
                        used_strings[i].append(sub_string)
                        new_header["cell_text"] = sub_string
                    if len(new_headers) <= o:
                        new_headers.append({})
                    new_headers[o][i] = new_header
            else:
                if len(new_headers) == 0:
                    new_headers.append({})
                new_headers[0][i] = header

        for i, row in enumerate(new_headers):
            for o in range(len(headers)):
                if o not in row:
                    blank_cell = headers[o].copy()
                    blank_cell["cell_text"] = ""
                    row[o] = blank_cell
            new_headers[i] = [row[o] for o in sorted(row)]

        return new_headers

    def _get_data_sections(self, json_data):
        data_sections = []
        for section in json_data["data_section"]:
            print(section)
            rows = []
            for row in section["data_rows"]:
                self.max_col_size = max(self.max_col_size, len(row))
                new_row = [[cell["cell_id"], cell["cell_text"]] for cell in row]
                rows.append(new_row)
            data_sections.append(["", rows])

        return data_sections

    def _get_headers_output(self):
        html = ""
        for row in self.headers:
            html += "<tr>"
            i = 0
            while i < len(row):
                cell = row[i]
                cell_id = cell.get("cell_id", "")
                content = cell["cell_text"]
                if cell_id in self.annotation_ids:
                    annot_id = next((a["id"] for a in self.table_annotations if a["locations"][0]["cell_id"] == cell_id), "")
                    cell_content = f"<div class='annotation' data-annot-id='{annot_id}'>{content}</div>"
                else:
                    cell_content = f"<div data-annot-id='{cell_id}'>{content}</div>"
                col_span = 1
                for o in range(i + 1, len(row)):
                    next_cell = row[o]
                    if content == next_cell["cell_text"] or (i not in self.piped_indexes and o not in self.piped_indexes and (not content or not next_cell["cell_text"])):
                        col_span += 1
                    else:
                        break
                html += f"<th colspan='{col_span}'>{cell_content}</th>"
                i += col_span
            html += "</tr>"
        return html

    def _get_caption_output(self):
        caption_text = self.caption
        added_char_length = 0
        for annot in self.caption_annotations:
            offset = annot["locations"][0]["offset"] + added_char_length
            length = annot["locations"][0]["length"]
            replace_str = f"<div class='annotation' data-annot-id='{annot['id']}'>{annot['text']}</div>"
            caption_text = caption_text[:offset] + replace_str + caption_text[offset + length:]
            added_char_length += len(replace_str) - length
        return f"<caption style='text-align:left'><br/><b>{self.title}</b><p>{caption_text}</p></caption>"

    def _get_table_num_output(self):
        return f"<p>Auto-CORPus id: {self.table_num}</p>"

    def _get_sections_output(self):
        html = ""
        for section in self.data_sections:
            section_title, rows = section
            if section_title:
                html += f"<tr><td colspan='{self.max_col_size}'><b>{section_title}</b></td></tr>"
            for row in rows:
                html += "<tr>"
                for cell_id, cell_text in row:
                    if cell_id in self.annotation_ids:
                        annot_id = next((a["id"] for a in self.table_annotations if a["locations"][0]["cell_id"] == cell_id), "")
                        cell_content = f"<div class='annotation' data-annot-id='{annot_id}'>{cell_text}</div>"
                    else:
                        cell_content = f"<div data-annot-id='{cell_id}'>{cell_text}</div>"
                    html += f"<td>{cell_content}</td>"
                html += "</tr>"
        return html

    def output_table(self):
        html = self._get_table_num_output()
        html += self._get_caption_output()
        html += "<div class='table-container'><table border=1 width='80%'><thead>"
        html += self._get_headers_output()
        html += "</thead><tbody>"
        html += self._get_sections_output()
        html += "</tbody></table></div>"
        if hasattr(self, 'table_footer') and self.table_footer:
            html += f"<p>{self.table_footer}</p>"
        return html
