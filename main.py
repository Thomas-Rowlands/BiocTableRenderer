import os
import json
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from converter import Paper  # Your converted class

app = FastAPI()

# Mount the static directory
app.mount("/static", StaticFiles(directory="static"), name="static")

# Set up templates
templates = Jinja2Templates(directory="templates")

@app.get("/", response_class=HTMLResponse)
async def display_tables(request: Request, pmcid: str):
    filename = f'static/TableFiles/{pmcid}_tables.json'

    if not os.path.exists(filename):
        return HTMLResponse(f"<h1>Error</h1><p>File not found: {filename}</p>", status_code=404)

    try:
        paper = Paper(filename, pmcid)
    except Exception as e:
        return HTMLResponse(f"<h1>Error</h1><p>{e}</p>", status_code=500)

    return templates.TemplateResponse("document_view.html", {
        "request": request,
        "table_html": paper.output_tables(),
        "document_relations": json.dumps(paper.relations),
        "document_annotations": json.dumps(paper.annotations),
    })

@app.get("/browse", response_class=HTMLResponse)
async def browse_files(request: Request):
    dir_path = os.path.join("static", "TableFiles")
    files = [f for f in os.listdir(dir_path) if f.endswith('_tables.json')]
    pmc_ids = sorted(set(f.replace('_tables.json', '') for f in files))

    return templates.TemplateResponse("browse.html", {
        "request": request,
        "pmc_ids": pmc_ids
    })
