# Autodesk APS BIM Models

The BIM viewer references two RVT models: Overpass Lingkar Pekanbaru and
Underpass Betung-Jambi. Their Autodesk public share links open the official
viewer in a separate tab because Autodesk blocks iframe embedding. Three.js
currently displays an interactive representative bridge model; it is not a
conversion of the RVT geometry.

To load the actual RVT geometry and properties inside the application's
Autodesk BIM mode, upload the files to your own Autodesk Platform Services
(APS) bucket and translate them to SVF2 as described below.

## Configure credentials

Create `backend/.env` (this file is ignored by Git) and add an APS application
client ID and secret. The APS application must have the Data Management and
Model Derivative API enabled.

```dotenv
FORGE_CLIENT_ID=your-client-id
FORGE_CLIENT_SECRET=your-client-secret
```

Do not put credentials in HTML, JavaScript, source control, or chat. The
application's server-side token endpoint only returns read/view scopes; the
upload command requests the additional write scopes it needs.

## Upload both models

Run from the repository root with the PHP executable installed by Laragon:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' backend\aps_upload_revit.php 'C:\Users\hp_7j\Downloads\OVERPASS PROYEK LINGKAR PEKANBARU STA 200+780.rvt.rvt' 'C:\Users\hp_7j\Downloads\JEMBATAN UNDERPASS PROYEK BETUNG JAMBI SEKSI 4 (1).rvt'
```

The command uploads both models, starts SVF2 translation, waits for each
translation to complete, and writes the resulting model URNs to `backend/.env`.
The RVT files are sent to Autodesk APS; confirm that the project data is
permitted to be stored there before running it. Large-model processing may take
several minutes and may incur APS usage charges.

After successful translation, sign in to the application and open BIM & WBS
Viewer. Select either model from the model selector. Clicking an element shows
its Revit name, external/Object ID, WBS when present, and available model
properties such as category, family/type, level, material, and dimensions.
