<?php
// Stub minimal pour Intelephense uniquement. Ne pas inclure à l'exécution.
// Ce fichier permet à l'analyseur de connaître les signatures de base d'Imagick
// lorsque l'extension n'est pas installée sur l'environnement de dev.
//
// IMPORTANT: NE PAS require/include ce fichier dans le runtime.
// Intelephense indexe les fichiers de l'espace de travail et utilisera ces définitions
// pour la complétion et la suppression des avertissements P1009.

if (!class_exists('Imagick')) {
    class Imagick
    {
        public const COLORSPACE_GRAY = 1;
        public const FILTER_LANCZOS = 22;

        public function __construct() {}
        /** @return bool */
        public function readImage(string $filename)
        {
            return true;
        }
        /** @return bool */
        public function autoOrient()
        {
            return true;
        }
        /** @return bool */
        public function setImageColorspace(int $colorspace)
        {
            return true;
        }
        /** @return bool */
        public function despeckleImage()
        {
            return true;
        }
        /** @return bool */
        public function enhanceImage()
        {
            return true;
        }
        /** @return bool */
        public function contrastStretchImage(float $blackPoint, float $whitePoint)
        {
            return true;
        }
        /** @return bool */
        public function deskewImage(float $threshold)
        {
            return true;
        }
        /** @return int */
        public function getImageWidth()
        {
            return 0;
        }
        /** @return int */
        public function getImageHeight()
        {
            return 0;
        }
        /** @return bool */
        public function resizeImage(int $columns, int $rows, int $filter, float $blur)
        {
            return true;
        }
        /** @return bool */
        public function setImageFormat(string $format)
        {
            return true;
        }
        /** @return bool */
        public function writeImage(string $filename)
        {
            return true;
        }
        /** @return bool */
        public function cropImage(int $width, int $height, int $x, int $y)
        {
            return true;
        }
        /** @return bool */
        public function setImagePage(int $width, int $height, int $x, int $y)
        {
            return true;
        }
        /** @return void */
        public function clear() {}
        /** @return void */
        public function destroy() {}
    }
}
