import os
import zipfile
from pathlib import Path

# Percorsi assoluti per evitare ogni dubbio
base_dir = Path(r"C:\py\CrazyAlbum\website\ffmpeg")
bin_dir = base_dir / "bin"
zips = ["ffmpeg.zip", "ffplay.zip", "ffprobe.zip"]

print("====================================================")
print("       TEST DI ESTRAZIONE BINARI FFmpeg            ")
print("====================================================")
print(f"Cartella base: {base_dir}")

if not base_dir.exists():
    print("\n❌ ERRORE: La cartella base non esiste!")
    print("Assicurati che il percorso C:\\py\\CrazyAlbum\\website\\ffmpeg sia corretto.")
    exit()

if not bin_dir.exists():
    print("\n[INFO] La cartella bin non esiste. Creazione in corso...")
    try:
        bin_dir.mkdir(parents=True, exist_ok=True)
        print(" [OK] Cartella bin creata.")
    except Exception as e:
        print(f" [ERRORE] Impossibile creare la cartella bin: {e}")
        exit()

print("\n--- Verifica e Estrazione ---")
for z in zips:
    zip_path = base_dir / z
    if zip_path.exists():
        print(f"🔍 {z}: TROVATO")
        try:
            with zipfile.ZipFile(zip_path, 'r') as zip_ref:
                zip_ref.extractall(bin_dir)
                print(f"   ✅ SUCCESS: {z} estratto in {bin_dir}")
        except Exception as e:
            print(f"   ❌ FAIL: Errore durante l'estrazione di {z}: {e}")
    else:
        print(f"🔍 {z}: NON TROVATO")

print("\n====================================================")
print("Test completato. Ora controlla se i file .exe sono presenti in:")
print(bin_dir)
print("====================================================")
input("\nPremi Invio per uscire...")
