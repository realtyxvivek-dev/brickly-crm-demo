<?php

namespace App\Support;

class TestLeadNameGenerator
{
    /**
     * Generate a clean Indian-style test lead name.
     */
    public static function make(): string
    {
        $firstNames = [
            'Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Reyansh',
            'Ananya', 'Diya', 'Ishita', 'Kavya', 'Meera',
            'Rohan', 'Rahul', 'Amit', 'Priya', 'Sneha',
            'Kunal', 'Nikita', 'Sanjay', 'Neha', 'Vikas',
        ];

        $lastNames = [
            'Sharma', 'Verma', 'Gupta', 'Yadav', 'Mishra',
            'Pandey', 'Singh', 'Tiwari', 'Agarwal', 'Chauhan',
            'Kapoor', 'Bansal', 'Mehta', 'Joshi', 'Saxena',
            'Tripathi', 'Reddy', 'Nair', 'Iyer', 'Malhotra',
        ];

        $first = $firstNames[array_rand($firstNames)];
        $last = $lastNames[array_rand($lastNames)];

        return "{$first} {$last} Test";
    }
}
