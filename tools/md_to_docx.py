"""
md_to_docx.py — minimal Markdown → DOCX converter for the MBSGuru project
documents. Handles the subset of Markdown actually used in our docs:

  - ATX headings (# .. ######) → Heading styles
  - Paragraphs with **bold** and *italic* / _italic_
  - Numbered + bulleted lists (one level; no nested-list rendering)
  - Fenced code blocks (```) → mono-paragraphs
  - Pipe-style tables  | col | col |
                       |-----|-----|
                       | a   | b   |
  - Horizontal rules (---) → page break (only when on its own line)
  - Inline `code` → mono runs

Usage:
    python tools/md_to_docx.py INPUT.md OUTPUT.docx [--title "Doc title"]

The converter is intentionally narrow — only the features we use. Anything
else falls through as plain paragraph text. Safe to extend.
"""

import sys
import re
import argparse
from pathlib import Path

from docx import Document
from docx.shared import Pt, Inches, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml.ns import qn
from docx.oxml import OxmlElement


# ─── inline run formatting ────────────────────────────────────────────
INLINE_RE = re.compile(
    r"(\*\*.+?\*\*|__.+?__|\*.+?\*|_.+?_|`.+?`)"
)


def add_inline_runs(paragraph, text):
    """Add a paragraph's runs, parsing inline **bold**, *italic*, `code`."""
    parts = INLINE_RE.split(text)
    for part in parts:
        if not part:
            continue
        if part.startswith("**") and part.endswith("**"):
            run = paragraph.add_run(part[2:-2])
            run.bold = True
        elif part.startswith("__") and part.endswith("__"):
            run = paragraph.add_run(part[2:-2])
            run.bold = True
        elif part.startswith("*") and part.endswith("*") and len(part) > 2:
            run = paragraph.add_run(part[1:-1])
            run.italic = True
        elif part.startswith("_") and part.endswith("_") and len(part) > 2:
            run = paragraph.add_run(part[1:-1])
            run.italic = True
        elif part.startswith("`") and part.endswith("`"):
            run = paragraph.add_run(part[1:-1])
            run.font.name = "Consolas"
            run.font.size = Pt(10)
            run.font.color.rgb = RGBColor(0x10, 0x40, 0x80)
        else:
            paragraph.add_run(part)


# ─── table shading helper ─────────────────────────────────────────────
def shade_cell(cell, hex_color):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), hex_color)
    tc_pr.append(shd)


def set_cell_border(cell):
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_borders = OxmlElement('w:tcBorders')
    for edge in ('top', 'left', 'bottom', 'right'):
        b = OxmlElement(f'w:{edge}')
        b.set(qn('w:val'), 'single')
        b.set(qn('w:sz'), '4')
        b.set(qn('w:color'), 'CCCCCC')
        tc_borders.append(b)
    tc_pr.append(tc_borders)


# ─── main parser ──────────────────────────────────────────────────────
def md_to_docx(md_path: Path, docx_path: Path, doc_title: str = None):
    text = md_path.read_text(encoding="utf-8")
    lines = text.splitlines()

    doc = Document()

    # ── Document-wide styles
    style = doc.styles['Normal']
    style.font.name = 'Calibri'
    style.font.size = Pt(11)

    # ── Title page
    if doc_title:
        title = doc.add_paragraph()
        title.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = title.add_run(doc_title)
        run.font.size = Pt(28)
        run.font.bold = True
        run.font.color.rgb = RGBColor(0x1C, 0x1A, 0x4A)

        # Add some space then a page break before the body
        for _ in range(3):
            doc.add_paragraph()
        meta = doc.add_paragraph()
        meta.alignment = WD_ALIGN_PARAGRAPH.CENTER
        meta_run = meta.add_run(
            "MBSGuru Mobile App Development Specification\n"
            "Version 1.0  ·  2026-05-12  ·  Engineering Team"
        )
        meta_run.font.size = Pt(11)
        meta_run.font.color.rgb = RGBColor(0x6B, 0x72, 0x80)

        doc.add_page_break()

    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        # ── Horizontal rule = section break
        if re.match(r"^-{3,}$", stripped):
            doc.add_paragraph()
            i += 1
            continue

        # ── Headings
        m = re.match(r"^(#{1,6})\s+(.*)$", stripped)
        if m:
            level = len(m.group(1))
            heading_text = m.group(2).strip()
            doc.add_heading(heading_text, level=min(level, 6))
            i += 1
            continue

        # ── Fenced code block
        if stripped.startswith("```"):
            i += 1
            code_lines = []
            while i < len(lines) and not lines[i].strip().startswith("```"):
                code_lines.append(lines[i])
                i += 1
            i += 1  # skip closing ```
            p = doc.add_paragraph()
            run = p.add_run("\n".join(code_lines))
            run.font.name = "Consolas"
            run.font.size = Pt(9)
            run.font.color.rgb = RGBColor(0x24, 0x29, 0x38)
            p.paragraph_format.left_indent = Inches(0.3)
            p.paragraph_format.space_before = Pt(6)
            p.paragraph_format.space_after = Pt(6)
            continue

        # ── Pipe table  (header | --- | row | row)
        if stripped.startswith("|") and stripped.endswith("|") and "|" in stripped[1:-1]:
            # Lookahead — next line should be a separator |---|---|
            if i + 1 < len(lines) and re.match(r"^\|\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|$", lines[i + 1].strip()):
                header_cells = [c.strip() for c in stripped.strip("|").split("|")]
                i += 2   # skip header + separator
                body_rows = []
                while i < len(lines) and lines[i].strip().startswith("|") and lines[i].strip().endswith("|"):
                    row_cells = [c.strip() for c in lines[i].strip().strip("|").split("|")]
                    body_rows.append(row_cells)
                    i += 1
                # Build table
                table = doc.add_table(rows=1 + len(body_rows), cols=len(header_cells))
                table.style = 'Light Grid Accent 1'
                hdr = table.rows[0].cells
                for col_idx, cell_text in enumerate(header_cells):
                    cell = hdr[col_idx]
                    cell.text = ""
                    para = cell.paragraphs[0]
                    run = para.add_run(cell_text)
                    run.bold = True
                    run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
                    shade_cell(cell, "5751E1")
                for r_idx, row in enumerate(body_rows, start=1):
                    cells = table.rows[r_idx].cells
                    for c_idx, cell_text in enumerate(row):
                        if c_idx >= len(cells):
                            break
                        cells[c_idx].text = ""
                        para = cells[c_idx].paragraphs[0]
                        add_inline_runs(para, cell_text)
                # Reasonable column widths
                doc.add_paragraph()
                continue

        # ── Bullet list
        if re.match(r"^\s*[-*]\s+", line):
            bullets = []
            while i < len(lines) and re.match(r"^\s*[-*]\s+", lines[i]):
                bullets.append(re.sub(r"^\s*[-*]\s+", "", lines[i]))
                i += 1
            for b in bullets:
                p = doc.add_paragraph(style='List Bullet')
                add_inline_runs(p, b)
            continue

        # ── Numbered list
        if re.match(r"^\s*\d+\.\s+", line):
            items = []
            while i < len(lines) and re.match(r"^\s*\d+\.\s+", lines[i]):
                items.append(re.sub(r"^\s*\d+\.\s+", "", lines[i]))
                i += 1
            for item in items:
                p = doc.add_paragraph(style='List Number')
                add_inline_runs(p, item)
            continue

        # ── Blank line
        if stripped == "":
            i += 1
            continue

        # ── Regular paragraph (collect lines until blank)
        para_lines = [stripped]
        i += 1
        while i < len(lines) and lines[i].strip() != "" and not lines[i].strip().startswith(("#", "-", "*", "|", "```", "0", "1", "2", "3", "4", "5", "6", "7", "8", "9")):
            para_lines.append(lines[i].strip())
            i += 1
        p = doc.add_paragraph()
        add_inline_runs(p, " ".join(para_lines))

    doc.save(str(docx_path))
    print(f"Wrote {docx_path}  ({docx_path.stat().st_size:,} bytes)")


# ─── CLI ──────────────────────────────────────────────────────────────
if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("input", help="Input .md file")
    parser.add_argument("output", help="Output .docx file")
    parser.add_argument("--title", help="Optional title page text", default=None)
    args = parser.parse_args()
    md_to_docx(Path(args.input), Path(args.output), args.title)
