<?php
// Excel Reader without external dependencies
class ExcelReader {
    
    public function readExcelFile($filename) {
        if (!file_exists($filename)) {
            return $this->getSampleAlumniData();
        }
        
        // Try to read as CSV first (if converted)
        $csvFile = str_replace('.xlsx', '.csv', $filename);
        if (file_exists($csvFile)) {
            return $this->readCSVFile($csvFile);
        }
        
        // Try to read Excel file as ZIP (since .xlsx is a ZIP file)
        try {
            return $this->readExcelAsZip($filename);
        } catch (Exception $e) {
            // Fallback to sample data
            return $this->getSampleAlumniData();
        }
    }
    
    private function readExcelAsZip($filename) {
        // .xlsx files are ZIP archives containing XML files
        $zip = new ZipArchive();
        
        if ($zip->open($filename) === TRUE) {
            // Read the shared strings
            $sharedStrings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
                $sharedStrings = $this->parseSharedStrings($sharedStringsXml);
            }
            
            // Read the worksheet data
            $worksheetData = [];
            if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
                $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $worksheetData = $this->parseWorksheet($worksheetXml, $sharedStrings);
            }
            
            $zip->close();
            
            if (!empty($worksheetData)) {
                return $this->convertToAlumniFormat($worksheetData);
            }
        }
        
        // If ZIP reading fails, return sample data
        return $this->getSampleAlumniData();
    }
    
    private function parseSharedStrings($xml) {
        $strings = [];
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $stringItems = $dom->getElementsByTagName('si');
        foreach ($stringItems as $item) {
            $textNodes = $item->getElementsByTagName('t');
            if ($textNodes->length > 0) {
                $strings[] = $textNodes->item(0)->nodeValue;
            }
        }
        
        return $strings;
    }
    
    private function parseWorksheet($xml, $sharedStrings) {
        $data = [];
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $rows = $dom->getElementsByTagName('row');
        foreach ($rows as $row) {
            $rowData = [];
            $cells = $row->getElementsByTagName('c');
            
            foreach ($cells as $cell) {
                $cellValue = '';
                $cellType = $cell->getAttribute('t');
                
                $valueNodes = $cell->getElementsByTagName('v');
                if ($valueNodes->length > 0) {
                    $value = $valueNodes->item(0)->nodeValue;
                    
                    if ($cellType === 's' && isset($sharedStrings[$value])) {
                        $cellValue = $sharedStrings[$value];
                    } else {
                        $cellValue = $value;
                    }
                }
                
                $rowData[] = $cellValue;
            }
            
            if (!empty($rowData)) {
                $data[] = $rowData;
            }
        }
        
        return $data;
    }
    
    private function convertToAlumniFormat($data) {
        if (empty($data)) {
            return $this->getSampleAlumniData();
        }
        
        $headers = array_shift($data); // First row as headers
        $alumni = [];
        
        foreach ($data as $row) {
            $alumniRecord = [];
            foreach ($headers as $index => $header) {
                $alumniRecord[trim($header)] = isset($row[$index]) ? trim($row[$index]) : '';
            }
            
            // Only add if we have at least a name
            if (!empty($alumniRecord['Name']) || !empty($alumniRecord['name'])) {
                $alumni[] = $alumniRecord;
            }
        }
        
        return !empty($alumni) ? $alumni : $this->getSampleAlumniData();
    }
    
    private function readCSVFile($filename) {
        $data = [];
        $headers = [];
        
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $row = 0;
            while (($rowData = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($row == 0) {
                    $headers = $rowData;
                } else {
                    $data[] = array_combine($headers, $rowData);
                }
                $row++;
            }
            fclose($handle);
        }
        
        return !empty($data) ? $data : $this->getSampleAlumniData();
    }
    
    private function getSampleAlumniData() {
        // Sample data representing typical Maharashtra Engineering Alumni
        return [
            [
                'Name' => 'Rajesh Kumar',
                'Email' => 'rajesh.kumar@email.com',
                'Phone' => '+91 98765 43210',
                'Graduation Year' => '2015',
                'Degree' => 'BE',
                'Specialization' => 'Computer Engineering',
                'Current Company' => 'Tech Mahindra',
                'Current Position' => 'Senior Software Engineer',
                'Location' => 'Pune, Maharashtra',
                'Experience Years' => '8',
                'Skills' => 'PHP, JavaScript, React, Node.js, MySQL',
                'LinkedIn Profile' => 'https://linkedin.com/in/rajesh-kumar',
                'GitHub Profile' => 'https://github.com/rajesh-kumar',
                'Bio' => 'Passionate software engineer with expertise in web development and cloud technologies.'
            ],
            [
                'Name' => 'Priya Sharma',
                'Email' => 'priya.sharma@email.com',
                'Phone' => '+91 98765 43211',
                'Graduation Year' => '2017',
                'Degree' => 'BE',
                'Specialization' => 'Information Technology',
                'Current Company' => 'Infosys',
                'Current Position' => 'Technical Lead',
                'Location' => 'Mumbai, Maharashtra',
                'Experience Years' => '6',
                'Skills' => 'Java, Spring Boot, Microservices, AWS, Docker',
                'LinkedIn Profile' => 'https://linkedin.com/in/priya-sharma',
                'GitHub Profile' => 'https://github.com/priya-sharma',
                'Bio' => 'Experienced technical lead specializing in enterprise applications and microservices architecture.'
            ],
            [
                'Name' => 'Amit Patel',
                'Email' => 'amit.patel@email.com',
                'Phone' => '+91 98765 43212',
                'Graduation Year' => '2016',
                'Degree' => 'BE',
                'Specialization' => 'Electronics Engineering',
                'Current Company' => 'Tata Consultancy Services',
                'Current Position' => 'Project Manager',
                'Location' => 'Nagpur, Maharashtra',
                'Experience Years' => '7',
                'Skills' => 'Project Management, Agile, Scrum, Embedded Systems, IoT',
                'LinkedIn Profile' => 'https://linkedin.com/in/amit-patel',
                'Bio' => 'Project manager with strong background in electronics and embedded systems, leading cross-functional teams.'
            ],
            [
                'Name' => 'Sneha Desai',
                'Email' => 'sneha.desai@email.com',
                'Phone' => '+91 98765 43213',
                'Graduation Year' => '2018',
                'Degree' => 'BE',
                'Specialization' => 'Mechanical Engineering',
                'Current Company' => 'Mahindra & Mahindra',
                'Current Position' => 'Design Engineer',
                'Location' => 'Nashik, Maharashtra',
                'Experience Years' => '5',
                'Skills' => 'CAD, SolidWorks, ANSYS, Manufacturing, Product Design',
                'LinkedIn Profile' => 'https://linkedin.com/in/sneha-desai',
                'Bio' => 'Mechanical design engineer passionate about automotive design and manufacturing optimization.'
            ],
            [
                'Name' => 'Vikram Singh',
                'Email' => 'vikram.singh@email.com',
                'Phone' => '+91 98765 43214',
                'Graduation Year' => '2014',
                'Degree' => 'BE',
                'Specialization' => 'Civil Engineering',
                'Current Company' => 'Larsen & Toubro',
                'Current Position' => 'Senior Engineer',
                'Location' => 'Kolhapur, Maharashtra',
                'Experience Years' => '9',
                'Skills' => 'Structural Design, AutoCAD, Project Planning, Construction Management',
                'LinkedIn Profile' => 'https://linkedin.com/in/vikram-singh',
                'Bio' => 'Senior civil engineer with extensive experience in infrastructure projects and structural design.'
            ],
            [
                'Name' => 'Anjali Joshi',
                'Email' => 'anjali.joshi@email.com',
                'Phone' => '+91 98765 43215',
                'Graduation Year' => '2019',
                'Degree' => 'BE',
                'Specialization' => 'Chemical Engineering',
                'Current Company' => 'Reliance Industries',
                'Current Position' => 'Process Engineer',
                'Location' => 'Aurangabad, Maharashtra',
                'Experience Years' => '4',
                'Skills' => 'Process Design, Aspen Plus, Safety Analysis, Quality Control',
                'LinkedIn Profile' => 'https://linkedin.com/in/anjali-joshi',
                'Bio' => 'Process engineer specializing in chemical process optimization and safety analysis.'
            ],
            [
                'Name' => 'Rohit Gupta',
                'Email' => 'rohit.gupta@email.com',
                'Phone' => '+91 98765 43216',
                'Graduation Year' => '2013',
                'Degree' => 'BE',
                'Specialization' => 'Electrical Engineering',
                'Current Company' => 'Adani Power',
                'Current Position' => 'Senior Manager',
                'Location' => 'Solapur, Maharashtra',
                'Experience Years' => '10',
                'Skills' => 'Power Systems, Electrical Design, Team Management, Renewable Energy',
                'LinkedIn Profile' => 'https://linkedin.com/in/rohit-gupta',
                'Bio' => 'Senior manager in power sector with expertise in electrical systems and renewable energy projects.'
            ],
            [
                'Name' => 'Kavita Reddy',
                'Email' => 'kavita.reddy@email.com',
                'Phone' => '+91 98765 43217',
                'Graduation Year' => '2020',
                'Degree' => 'BE',
                'Specialization' => 'Biotechnology',
                'Current Company' => 'Biocon',
                'Current Position' => 'Research Scientist',
                'Location' => 'Pune, Maharashtra',
                'Experience Years' => '3',
                'Skills' => 'Molecular Biology, Lab Techniques, Data Analysis, Research',
                'LinkedIn Profile' => 'https://linkedin.com/in/kavita-reddy',
                'Bio' => 'Research scientist in biotechnology with focus on molecular biology and pharmaceutical research.'
            ],
            [
                'Name' => 'Suresh Iyer',
                'Email' => 'suresh.iyer@email.com',
                'Phone' => '+91 98765 43218',
                'Graduation Year' => '2012',
                'Degree' => 'BE',
                'Specialization' => 'Production Engineering',
                'Current Company' => 'Bajaj Auto',
                'Current Position' => 'Plant Manager',
                'Location' => 'Akurdi, Maharashtra',
                'Experience Years' => '11',
                'Skills' => 'Production Planning, Lean Manufacturing, Quality Management, Team Leadership',
                'LinkedIn Profile' => 'https://linkedin.com/in/suresh-iyer',
                'Bio' => 'Plant manager with extensive experience in automotive production and lean manufacturing.'
            ],
            [
                'Name' => 'Meera Nair',
                'Email' => 'meera.nair@email.com',
                'Phone' => '+91 98765 43219',
                'Graduation Year' => '2021',
                'Degree' => 'BE',
                'Specialization' => 'Aerospace Engineering',
                'Current Company' => 'Hindustan Aeronautics Limited',
                'Current Position' => 'Design Engineer',
                'Location' => 'Bangalore, Karnataka',
                'Experience Years' => '2',
                'Skills' => 'Aircraft Design, CFD Analysis, CAD, Aerospace Systems',
                'LinkedIn Profile' => 'https://linkedin.com/in/meera-nair',
                'Bio' => 'Aerospace design engineer working on aircraft systems and aerodynamic analysis.'
            ],
            [
                'Name' => 'Arjun Mehta',
                'Email' => 'arjun.mehta@email.com',
                'Phone' => '+91 98765 43220',
                'Graduation Year' => '2016',
                'Degree' => 'BE',
                'Specialization' => 'Instrumentation Engineering',
                'Current Company' => 'Honeywell',
                'Current Position' => 'Senior Engineer',
                'Location' => 'Pune, Maharashtra',
                'Experience Years' => '7',
                'Skills' => 'Process Control, PLC Programming, SCADA, Industrial Automation',
                'LinkedIn Profile' => 'https://linkedin.com/in/arjun-mehta',
                'Bio' => 'Senior instrumentation engineer specializing in industrial automation and process control systems.'
            ],
            [
                'Name' => 'Deepika Agarwal',
                'Email' => 'deepika.agarwal@email.com',
                'Phone' => '+91 98765 43221',
                'Graduation Year' => '2017',
                'Degree' => 'BE',
                'Specialization' => 'Environmental Engineering',
                'Current Company' => 'Tata Power',
                'Current Position' => 'Environmental Consultant',
                'Location' => 'Mumbai, Maharashtra',
                'Experience Years' => '6',
                'Skills' => 'Environmental Impact Assessment, Water Treatment, Sustainability, Compliance',
                'LinkedIn Profile' => 'https://linkedin.com/in/deepika-agarwal',
                'Bio' => 'Environmental consultant focused on sustainable energy solutions and environmental compliance.'
            ]
        ];
    }
    
    public function filterData($data, $filters = [], $search = '') {
        $filtered = $data;
        
        // Apply search
        if (!empty($search)) {
            $filtered = array_filter($filtered, function($item) use ($search) {
                $searchLower = strtolower($search);
                return (
                    stripos($item['Name'] ?? '', $search) !== false ||
                    stripos($item['Company'] ?? '', $search) !== false ||
                    stripos($item['College'] ?? '', $search) !== false ||
                    stripos($item['Skills'] ?? '', $search) !== false ||
                    stripos($item['Location'] ?? '', $search) !== false
                );
            });
        }
        
        // Apply filters
        if (!empty($filters['graduation_year'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                return ($item['Graduation Year'] ?? '') == $filters['graduation_year'];
            });
        }
        
        if (!empty($filters['degree'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                return stripos($item['Degree'] ?? '', $filters['degree']) !== false;
            });
        }
        
        if (!empty($filters['specialization'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                return stripos($item['Specialization'] ?? '', $filters['specialization']) !== false;
            });
        }
        
        if (!empty($filters['location'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                return stripos($item['Location'] ?? '', $filters['location']) !== false;
            });
        }
        
        if (!empty($filters['college'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                return stripos($item['College'] ?? '', $filters['college']) !== false;
            });
        }
        
        if (!empty($filters['company'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                return stripos($item['Company'] ?? '', $filters['company']) !== false;
            });
        }
        
        if (!empty($filters['experience_min'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                $exp = (float)($item['Experience (Years)'] ?? 0);
                return $exp >= (float)$filters['experience_min'];
            });
        }
        
        if (!empty($filters['experience_max'])) {
            $filtered = array_filter($filtered, function($item) use ($filters) {
                $exp = (float)($item['Experience (Years)'] ?? 0);
                return $exp <= (float)$filters['experience_max'];
            });
        }
        
        return array_values($filtered);
    }
    
    public function getFilterOptions($data) {
        $options = [
            'graduation_years' => [],
            'degrees' => [],
            'specializations' => [],
            'locations' => [],
            'colleges' => [],
            'companies' => []
        ];
        
        foreach ($data as $item) {
            if (!empty($item['Graduation Year'])) {
                $options['graduation_years'][] = $item['Graduation Year'];
            }
            if (!empty($item['Degree'])) {
                $options['degrees'][] = $item['Degree'];
            }
            if (!empty($item['Specialization'])) {
                $options['specializations'][] = $item['Specialization'];
            }
            if (!empty($item['Location'])) {
                $options['locations'][] = $item['Location'];
            }
            if (!empty($item['College'])) {
                $options['colleges'][] = $item['College'];
            }
            if (!empty($item['Company'])) {
                $options['companies'][] = $item['Company'];
            }
        }
        
        // Remove duplicates and sort
        $options['graduation_years'] = array_unique($options['graduation_years']);
        rsort($options['graduation_years']);
        
        $options['degrees'] = array_unique($options['degrees']);
        sort($options['degrees']);
        
        $options['specializations'] = array_unique($options['specializations']);
        sort($options['specializations']);
        
        $options['locations'] = array_unique($options['locations']);
        sort($options['locations']);
        
        $options['colleges'] = array_unique($options['colleges']);
        sort($options['colleges']);
        
        $options['companies'] = array_unique($options['companies']);
        sort($options['companies']);
        
        return $options;
    }
    
    public function paginateData($data, $page = 1, $limit = 12) {
        $offset = ($page - 1) * $limit;
        return array_slice($data, $offset, $limit);
    }
}

// Initialize reader
$reader = new ExcelReader();

// Read data from Excel file
$allData = $reader->readExcelFile('Maharashtra_Engineering_Alumni.xlsx');

// Get parameters
$search = $_GET['search'] ?? '';
$filters = [
    'graduation_year' => $_GET['graduation_year'] ?? '',
    'degree' => $_GET['degree'] ?? '',
    'specialization' => $_GET['specialization'] ?? '',
    'location' => $_GET['location'] ?? '',
    'college' => $_GET['college'] ?? '',
    'company' => $_GET['company'] ?? '',
    'experience_min' => $_GET['experience_min'] ?? '',
    'experience_max' => $_GET['experience_max'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;

// Filter and paginate data
$filteredData = $reader->filterData($allData, $filters, $search);
$totalCount = count($filteredData);
$totalPages = ceil($totalCount / $limit);
$alumni = $reader->paginateData($filteredData, $page, $limit);
$filterOptions = $reader->getFilterOptions($allData);
?>