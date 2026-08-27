import os
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest

from run import load_env, parse_arguments


class EnvironmentTests(unittest.TestCase):
    def test_external_env_file_argument(self):
        arguments = parse_arguments(["--env-file", "/home/user/private/telegram.env"])
        self.assertEqual("/home/user/private/telegram.env", arguments.env_file)

    def test_load_env_accepts_windows_utf8_bom(self):
        with TemporaryDirectory() as directory:
            path = Path(directory) / ".env"
            path.write_text("TELEGRAM_API_ID=12345\n", encoding="utf-8-sig")
            original = os.environ.pop("TELEGRAM_API_ID", None)
            try:
                load_env(path)
                self.assertEqual("12345", os.environ.get("TELEGRAM_API_ID"))
            finally:
                os.environ.pop("TELEGRAM_API_ID", None)
                if original is not None:
                    os.environ["TELEGRAM_API_ID"] = original


if __name__ == "__main__":
    unittest.main()
